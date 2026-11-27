<?php
declare(strict_types=1);
namespace App\Services;

use DomainException;
use PDO;
use PDOException;
use Throwable;

require_once __DIR__ . '/ProductVariantInput.php';
require_once __DIR__ . '/SkuService.php';
require_once __DIR__ . '/ProductVariantWriter.php';

/** Moteur 4B, sans route/UI. Toutes les mutations passent par le verrou produit. */
final class AdminProductVariantService
{
    private readonly ProductVariantWriter $writer;
    private readonly SkuService $skus;

    public function __construct(private readonly PDO $database, private readonly AdminAccessService $access, MoneyService $money)
    {
        $this->writer = new ProductVariantWriter($database,$access,$money);
        $this->skus = new SkuService($database);
    }

    /**
     * Première définition / conversion explicite. Definitions: [{nom,valeurs:[libellé,...]}].
     * Chaque ligne sélectionne {positionOption:positionValeur} (positions 1-based).
     * Toutes les anciennes variantes doivent être explicitement conservées par leur ID.
     */
    public function initialize(int $productId, array $definitions, array $variants, ?array $commonPrice = null, ?string $request = null): array
    {
        return $this->transaction($productId, fn(): array => $this->writer->initializeLocked($productId,$definitions,$variants,$commonPrice),['initialize',$request,[$definitions,$variants,$commonPrice]]);
    }

    public function authorize(): void { $this->access->requireCurrentAdmin(); }

    public function form(int $productId): array
    {
        $this->authorize();
        if ($this->rows('SELECT id_produit FROM produit WHERE id_produit=?',[$productId])===[]) throw new DomainException('Produit introuvable.',404);
        $available=(int)$this->database->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('option_produit','valeur_option_produit','variante_valeur_option')")->fetchColumn()===3
            && (int)$this->database->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='variante_produit' AND COLUMN_NAME='cle_combinaison'")->fetchColumn()===1;
        $definitions=[];
        if ($available) foreach ($this->rows('SELECT id_option,nom,position FROM option_produit WHERE id_produit=? ORDER BY position,id_option',[$productId]) as $option) {
            $option['valeurs']=$this->rows('SELECT id_valeur,libelle FROM valeur_option_produit WHERE id_option=? ORDER BY ordre_affichage,id_valeur',[$option['id_option']]);
            $definitions[]=$option;
        }
        $variants=$this->rows('SELECT * FROM variante_produit WHERE id_produit=? ORDER BY id_variante',[$productId]);
        $candidates=[];$candidateError=null;
        if ($definitions!==[]) {
            try {
            $preview=$this->writer->candidates(array_map(static fn(array $d):array=>['nom'=>$d['nom'],'valeurs'=>array_column($d['valeurs'],'libelle')],$definitions));
            foreach ($preview['candidates'] as $token=>$candidate) {
                $key=$this->storedSelectionKey($definitions,$candidate['selection']);
                $candidate['existing']=in_array($key,array_column($variants,'cle_combinaison'),true);
                $candidates[$token]=$candidate;
            }
            } catch (ProductFormValidationException $error) { $candidateError=$error->getMessage(); }
        }
        return compact('available','definitions','variants','candidates','candidateError');
    }

    /** Prévisualisation bornée et revalidation serveur, sans aucun INSERT. */

    public function candidates(array $definitions): array
    {
        $this->authorize();
        return $this->writer->candidates($definitions);
    }

    /** Politique 4C-1 : prix source sous verrou, stock zéro et nouvelles variantes inactives. */
    public function configureVersions(int $productId, array $definitions, array $selected, string $reuse, ?string $request = null): array
    {
        return $this->transaction($productId,function()use($productId,$definitions,$selected,$reuse):array {
            $preview=$this->writer->candidates($definitions);
            if (!array_is_list($selected)||$selected===[]||count($selected)>100||count(array_unique($selected,SORT_REGULAR))!==count($selected)) $this->invalid('Choisissez les combinaisons vendues, sans doublon.');
            if (!in_array($reuse,$selected,true)) throw new ProductFormValidationException('reuse','Choisissez explicitement la combinaison qui conserve la version existante.');
            $existing=$this->writer->lockedVariants($productId);
            if (count($existing)!==1) $this->invalid('Cette conversion exige un produit simple avec une seule version existante.');
            $old=reset($existing);
            if ($old['devise_reference']!=='USD') $this->invalid('Le prix de référence de la version existante doit être défini en USD avant conversion.');
            $variants=[];
            foreach ($selected as $token) {
                if (!is_string($token)||!isset($preview['candidates'][$token])) $this->invalid('Une combinaison sélectionnée ne correspond pas aux options.');
                $row=['selection'=>$preview['candidates'][$token]['selection']];
                if ($token===$reuse) $row['id_variante']=(int)$old['id_variante'];
                else $row+=['prix'=>$old['prix_reference'],'devise'=>'USD','stock'=>0,'statut'=>'inactif'];
                $variants[]=$row;
            }
            return $this->writer->initializeLocked($productId,$preview['definitions'],$variants);
        },['configure',$request,[$definitions,$selected,$reuse]]);
    }

    /** Ajout uniquement : décocher une version existante ne la supprime/désactive jamais. */
    public function extendVersions(int $productId, array $selected, ?string $request = null): array
    {
        return $this->transaction($productId,function()use($productId,$selected):array {
            $data=$this->form($productId);
            if ($data['definitions']===[]) $this->invalid('Définissez d’abord les options du produit.');
            if (!array_is_list($selected)||$selected===[]||count($selected)>100) $this->invalid('Choisissez au moins une nouvelle combinaison.');
            $existing=$this->writer->lockedVariants($productId);$source=reset($existing);
            if (!$source||$source['devise_reference']!=='USD') $this->invalid('Le prix de référence doit être défini en USD.');
            $lines=[];$seen=[];
            foreach ($selected as $token) {
                if (!is_string($token)||!isset($data['candidates'][$token])||isset($seen[$token])) $this->invalid('Combinaison invalide ou répétée.');
                $seen[$token]=true;
                if ($data['candidates'][$token]['existing']) continue;
                $values=[];
                foreach ($data['candidates'][$token]['selection'] as $option=>$value) $values[(int)$data['definitions'][$option-1]['id_option']]=(int)$data['definitions'][$option-1]['valeurs'][$value-1]['id_valeur'];
                $lines[]=['valeurs'=>$values,'prix'=>$source['prix_reference'],'devise'=>'USD','stock'=>0,'statut'=>'inactif'];
            }
            return $lines===[]?[]:$this->writer->persist($productId,$lines,null,$existing,false);
        },['extend',$request,[$selected]]);
    }

    private function storedSelectionKey(array $definitions, array $selection): string
    {
        $pairs=[];
        foreach ($selection as $option=>$value) $pairs[(int)$definitions[$option-1]['id_option']]=(int)$definitions[$option-1]['valeurs'][$value-1]['id_valeur'];
        ksort($pairs,SORT_NUMERIC);$parts=[];
        foreach ($pairs as $option=>$value) $parts[]=$option.':'.$value;
        return implode('|',$parts);
    }

    /** Lot choisi explicitement, jamais un produit cartésien. Prix commun facultatif {prix,devise}. */
    public function saveVariants(int $productId, array $variants, ?array $commonPrice = null, ?string $request = null): array
    {
        return $this->transaction($productId, fn(): array => $this->writer->persist($productId,$variants,$commonPrice,$this->writer->lockedVariants($productId),false),['save',$request,[$variants,$commonPrice]]);
    }

    public function addValue(int $productId, int $optionId, string $label, int $position = 0, bool $limitCandidates = false): int
    {
        return $this->transaction($productId, function() use ($productId,$optionId,$label,$position,$limitCandidates): int {
            $this->ownedOption($productId,$optionId);
            if ($limitCandidates) {
                $data=$this->form($productId);$definitions=[];
                foreach ($data['definitions'] as $definition) {
                    $values=array_column($definition['valeurs'],'libelle');
                    if ((int)$definition['id_option']===$optionId) $values[]=$label;
                    $definitions[]=['nom'=>$definition['nom'],'valeurs'=>$values];
                }
                $this->writer->candidates($definitions);
            }
            $this->execute('INSERT INTO valeur_option_produit(id_option,libelle,ordre_affichage) VALUES(?,?,?)', [$optionId,$this->label($label),$this->integer($position,0,32767)]);
            return (int)$this->database->lastInsertId();
        });
    }

    public function renameOption(int $productId, int $optionId, string $name): void
    {
        $this->transaction($productId, function() use ($productId,$optionId,$name): void {
            $this->ownedOption($productId,$optionId);
            $this->execute('UPDATE option_produit SET nom=? WHERE id_option=?', [$this->label($name),$optionId]);
        });
    }

    public function renameValue(int $productId, int $optionId, int $valueId, string $label): void
    {
        $this->transaction($productId, function() use ($productId,$optionId,$valueId,$label): void {
            $this->ownedOption($productId,$optionId);
            if ($this->rows('SELECT id_valeur FROM valeur_option_produit WHERE id_option=? AND id_valeur=?',[$optionId,$valueId]) === []) $this->invalid('Valeur introuvable.');
            $this->execute('UPDATE valeur_option_produit SET libelle=? WHERE id_valeur=?',[$this->label($label),$valueId]);
            $options = $this->writer->options($productId);
            foreach ($this->writer->lockedVariants($productId) as $id => $row) {
                if ($row['cle_combinaison'] === null || $row['cle_combinaison'] === 'simple') continue;
                $values = $this->writer->associations($id);
                [, $description] = $this->writer->combination($options,$values);
                $this->execute('UPDATE variante_produit SET description_variante=? WHERE id_variante=?',[$description,$id]);
            }
        });
    }

    /** Seules les valeurs non utilisées peuvent être retirées ; jamais de cascade. */
    public function removeValue(int $productId, int $optionId, int $valueId): void
    {
        $this->transaction($productId, function() use ($productId,$optionId,$valueId): void {
            $this->ownedOption($productId,$optionId);
            if ($this->rows('SELECT id_valeur FROM valeur_option_produit WHERE id_option=? AND id_valeur=?',[$optionId,$valueId])===[]) $this->invalid('Cette valeur n’appartient pas à l’option du produit.');
            if ($this->rows('SELECT id_variante FROM variante_valeur_option WHERE id_option=? AND id_valeur=? LIMIT 1',[$optionId,$valueId]) !== []) $this->invalid('Cette valeur est utilisée. Désactivez la variante plutôt que supprimer sa définition.');
            $this->execute('DELETE FROM valeur_option_produit WHERE id_option=? AND id_valeur=?',[$optionId,$valueId]);
        });
    }

    /** Retirer une option changerait l'identité/completude de toutes les combinaisons. */
    public function removeOption(int $productId, int $optionId): void
    {
        $this->transaction($productId, function() use ($productId,$optionId): void {
            $this->ownedOption($productId,$optionId);
            if ($this->writer->lockedVariants($productId) !== []) $this->invalid('Une option de variantes existantes ne peut pas être supprimée.');
            $this->execute('DELETE FROM valeur_option_produit WHERE id_option=?',[$optionId]);
            $this->execute('DELETE FROM option_produit WHERE id_option=?',[$optionId]);
        });
    }

    private function transaction(int $productId, callable $action, ?array $intent = null): mixed
    {
        $this->access->requireCurrentAdmin();
        if ($this->database->inTransaction()) throw new \LogicException('Le moteur doit posséder sa transaction.');
        CatalogueWriteLock::acquire($this->database);
        try {
        $operation=$intent===null?null:$this->skus->operation('variant:'.$productId.':'.$intent[0],$intent[1],$intent[2]);
        $this->database->beginTransaction();
        try {
            $this->access->requireCurrentAdmin(true);
            if ($productId < 1 || $this->rows('SELECT id_produit FROM produit WHERE id_produit=? FOR UPDATE',[$productId]) === []) throw new DomainException('Produit introuvable.',404);
            if ($operation!==null && ($replay=$this->skus->replay($operation))!==null) {
                $this->database->commit();return $replay;
            }
            $this->writer->useOperation($operation);
            $result = $action();
            if ($operation!==null) $this->skus->complete($operation,$result);
            $this->database->commit();
            return $result;
        } catch (Throwable $error) {
            if ($this->database->inTransaction()) $this->database->rollBack();
            if ($error instanceof PDOException) {
                $code = (int)($error->errorInfo[1] ?? 0);
                if ($code === 1062) throw new DomainException('SKU, combinaison ou libellé déjà utilisé. Choisissez une valeur distincte.',422,$error);
                if (in_array($code,[1213,1205],true)) throw new DomainException('Modification concurrente. Rechargez puis réessayez.',409,$error);
            }
            throw $error;
        }
        } finally { $this->writer->useOperation(null);CatalogueWriteLock::release($this->database); }
    }

    private function ownedOption(int $productId, int $id): void
    {
        if ($this->rows('SELECT id_option FROM option_produit WHERE id_produit=? AND id_option=?',[$productId,$id]) === []) $this->invalid('Cette option n’appartient pas au produit.');
    }

    private function label(mixed $value): string { return $this->writer->label($value); }
    private function integer(mixed $value, int $min, int $max): int { return $this->writer->integer($value,$min,$max); }
    private function fields(array $row, array $allowed): void { $this->writer->fields($row,$allowed); }
    private function invalid(string $message): never { throw new DomainException($message,422); }
    private function execute(string $sql, array $params): void { $query=$this->database->prepare($sql);$query->execute($params); }
    private function rows(string $sql, array $params): array { $query=$this->database->prepare($sql);$query->execute($params);return $query->fetchAll(PDO::FETCH_ASSOC); }
}
