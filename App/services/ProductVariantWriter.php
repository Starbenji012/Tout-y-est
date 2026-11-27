<?php
declare(strict_types=1);
namespace App\Services;

use DomainException;
use PDO;

require_once __DIR__ . '/ProductVariantInput.php';
require_once __DIR__ . '/SkuService.php';

/** Internal catalogue writer. Never begins, commits or rolls back the caller's transaction.
 * The owning service retains the catalogue lock, replay and completion boundary.
 */
final class ProductVariantWriter
{
    private readonly ProductVariantInput $input;
    private readonly SkuService $skus;
    private ?string $skuOperation=null;
    private int $skuItem=0;

    public function __construct(private readonly PDO $database, private readonly AdminAccessService $access, MoneyService $money)
    {
        $this->input=new ProductVariantInput($money);
        $this->skus=new SkuService($database);
    }

    public function useOperation(?string $operation): void
    {
        $this->skuOperation=$operation;
        $this->skuItem=0;
    }

    private function requireTransaction(int $productId): void
    {
        if (!$this->database->inTransaction()) throw new \LogicException('Transaction catalogue requise.');
        $lock=$this->database->prepare('SELECT IS_USED_LOCK(?)=CONNECTION_ID()');
        $lock->execute([substr(CatalogueWriteLock::name($this->database),0,64)]);
        if ((int)$lock->fetchColumn()!==1) throw new \LogicException('Verrou catalogue requis.');
        $this->access->requireCurrentAdmin(true);
        if ($this->rows('SELECT id_produit FROM produit WHERE id_produit=? FOR UPDATE',[$productId])===[]) throw new DomainException('Produit introuvable.',404);
    }

    /** Existing simple-product contract: NULL combination key and active technical variant. */
    public function createSimple(int $productId,string $sku,string $price,int $stock): int
    {
        $this->requireTransaction($productId);
        return $this->insertVariant($productId,$sku,$price,$stock,'actif',null,null);
    }

    public function initializeLocked(int $productId, array $definitions, array $variants, ?array $commonPrice = null): array
    {
        $this->requireTransaction($productId);
            if ($this->rows('SELECT * FROM option_produit WHERE id_produit=?', [$productId]) !== []) $this->invalid('Les options existent déjà.');
            if (count($definitions) < 1 || count($definitions) > 3 || !array_is_list($definitions)) $this->invalid('Choisissez de une à trois options.');
            $existing = $this->lockedVariants($productId);
            foreach ($existing as $row) {
                if ($row['cle_combinaison'] !== null && $row['cle_combinaison'] !== 'simple') $this->invalid('Une combinaison existante ne peut pas être réaffectée.');
            }
            $preserved = [];
            foreach ($variants as $row) {
                if (!is_array($row)) $this->invalid('Variante invalide.');
                if (isset($row['id_variante'])) $preserved[] = $this->integer($row['id_variante'], 1, PHP_INT_MAX);
            }
            $expected = array_keys($existing); sort($expected); sort($preserved);
            if ($expected !== $preserved) $this->invalid('Associez explicitement chaque variante existante, sans la recréer.');
            $mapping = [];
            foreach ($definitions as $index => $definition) {
                if (!is_array($definition)) $this->invalid('Option invalide.');
                $this->fields($definition, ['nom','valeurs']);
                $name = $this->label($definition['nom'] ?? null);
                $values = $definition['valeurs'] ?? null;
                if (!is_array($values) || !array_is_list($values) || $values === [] || count($values) > 32768) $this->invalid('Indiquez les valeurs de chaque option.');
                $this->execute('INSERT INTO option_produit(id_produit,nom,position) VALUES(?,?,?)', [$productId,$name,$index+1]);
                $optionId = (int)$this->database->lastInsertId();
                foreach ($values as $position => $value) {
                    $this->execute('INSERT INTO valeur_option_produit(id_option,libelle,ordre_affichage) VALUES(?,?,?)', [$optionId,$this->label($value),$position]);
                    $mapping[$index+1][$position+1] = [$optionId,(int)$this->database->lastInsertId()];
                }
            }
            foreach ($variants as &$row) {
                $selection = $row['selection'] ?? null;
                if (!is_array($selection) || count($selection) !== count($definitions)) $this->invalid('Chaque combinaison doit renseigner toutes les options.');
                $row['valeurs'] = [];
                foreach ($selection as $optionPosition => $valuePosition) {
                    $optionPosition = $this->integer($optionPosition,1,3);
                    $valuePosition = $this->integer($valuePosition,1,32768);
                    if (!isset($mapping[$optionPosition][$valuePosition])) $this->invalid('Sélection étrangère aux options définies.');
                    [$option,$value] = $mapping[$optionPosition][$valuePosition];
                    $row['valeurs'][$option] = $value;
                }
                unset($row['selection']);
            }
            unset($row);
            return ['options'=>$mapping, 'variantes'=>$this->persist($productId,$variants,$commonPrice,$existing,true)];
    }

    public function candidates(array $definitions): array
    {
        $this->access->requireCurrentAdmin();
        if (!array_is_list($definitions) || count($definitions)<1 || count($definitions)>3) throw new ProductFormValidationException('options','Ajoutez de une à trois options.');
        $normalized=[];$names=[];$count=1;$errors=[];
        foreach ($definitions as $index=>$definition) {
            if (!is_array($definition)) throw new ProductFormValidationException('options','Option invalide.');
            $this->fields($definition,['nom','valeurs']);
            try {$name=$this->label($definition['nom']??null);} catch(DomainException $e) {$errors['options.'.$index.'.nom']=$e->getMessage();$name='';}
            $values=$definition['valeurs']??null;
            if (!is_array($values)||!array_is_list($values)||count($values)<1||count($values)>100) {$errors['options.'.$index.'.valeurs']='Indiquez de une à cent valeurs.';$values=[];}
            $labels=[];
            foreach ($values as $valueIndex=>$value) {
                try {$labels[]=$this->label($value);}catch(DomainException $e){$errors['options.'.$index.'.valeurs.'.$valueIndex]=$e->getMessage();}
            }
            if ($labels!==[] && $this->duplicateLabels($labels)) $errors['options.'.$index.'.valeurs']='Chaque valeur doit être différente.';
            $names[]=$name;$normalized[]=['nom'=>$name,'valeurs'=>$labels];
            $count*=max(1,count($values));
        }
        if ($this->duplicateLabels($names)) $errors['options']='Chaque option doit avoir un nom différent.';
        if ($count>100) $errors['options']='Ces options créent plus de 100 combinaisons. Réduisez le nombre de valeurs.';
        if ($errors!==[]) throw new ProductFormValidationException($errors);
        $candidates=[''=>['label'=>'','selection'=>[]]];
        foreach ($normalized as $i=>$definition) {
            $next=[];
            foreach ($candidates as $token=>$candidate) foreach ($definition['valeurs'] as $j=>$label) {
                $next[ltrim($token.'|'.($i+1).':'.($j+1),'|')]=['label'=>$candidate['label']===''?$label:$candidate['label'].' / '.$label,'selection'=>$candidate['selection']+[$i+1=>$j+1]];
            }
            $candidates=$next;
        }
        return ['definitions'=>$normalized,'candidates'=>$candidates,'count'=>$count];
    }

    private function duplicateLabels(array $labels): bool
    {
        if (count($labels)<2) return false;
        $sql=implode(' UNION ALL ',array_fill(0,count($labels),'SELECT CAST(? AS CHAR CHARACTER SET utf8mb4) AS label'));
        return $this->rows('SELECT COUNT(*) FROM ('.$sql.') labels GROUP BY label COLLATE utf8mb4_unicode_ci HAVING COUNT(*)>1 LIMIT 1',$labels)!==[];
    }

    public function persist(int $productId, array $variants, ?array $commonPrice, array $existing, bool $conversion): array
    {
        $this->requireTransaction($productId);
        if (!array_is_list($variants) || $variants === [] || count($variants) > 100) $this->invalid('Indiquez de une à cent variantes.');
        $price = null;
        if ($commonPrice !== null) {
            $this->fields($commonPrice,['prix','devise']);
            $price = $this->price($commonPrice);
        }
        $options = $this->options($productId);
        $newCount = 0; $seen = []; $keys = []; $prepared = [];
        foreach ($variants as $row) {
            if (!is_array($row)) $this->invalid('Variante invalide.');
            $this->fields($row,['id_variante','valeurs','sku','prix','devise','stock','stock_attendu','statut']);
            $id = isset($row['id_variante']) ? $this->integer($row['id_variante'],1,PHP_INT_MAX) : null;
            $old = $id === null ? null : ($existing[$id] ?? null);
            if ($id !== null && $old === null) $this->invalid('Cette variante n’appartient pas au produit.');
            if ($id !== null && isset($seen[$id])) $this->invalid('Variante répétée dans la demande.');
            if ($id !== null) $seen[$id] = true; else $newCount++;
            $values = $row['valeurs'] ?? ($id === null ? [] : $this->associations($id));
            if (!is_array($values)) $this->invalid('Combinaison invalide.');
            [$key,$description] = $this->combination($options,$values);
            if ($old !== null && !$conversion && $old['cle_combinaison'] !== null && $old['cle_combinaison'] !== $key) $this->invalid('L’identité d’une variante existante ne peut pas changer.');
            if (isset($keys[$key])) $this->invalid('Cette combinaison est déjà présente.');
            $keys[$key] = true;
            foreach ($existing as $otherId => $other) {
                if ($otherId !== $id && $other['cle_combinaison'] === $key) $this->invalid('Cette combinaison existe déjà.');
            }
            $sku = isset($row['sku']) ? $this->input->sku($this->text($row['sku'])) : ($old['sku'] ?? '');
            if ($sku === '') $sku = $old['sku'] ?? '';
            $reservation=null;
            if ($price !== null && (isset($row['prix']) || isset($row['devise']))) $this->invalid('Choisissez un prix commun ou des prix individuels, pas les deux.');
            $reference = $price ?? ((isset($row['prix']) || isset($row['devise'])) ? $this->price($row) : ($old['prix_reference'] ?? null));
            if ($reference === null) $this->invalid('Indiquez le prix de chaque variante.');
            // Une ancienne devise ambiguë ne doit jamais être rebaptisée USD silencieusement.
            if ($old !== null && $old['devise_reference'] !== 'USD' && $price === null && !isset($row['prix'])) $this->invalid('Précisez le prix USD/CDF de cette variante historique.');
            $stock = $old === null ? $this->integer($row['stock'] ?? null,0,4294967295) : (int)$old['stock'];
            if ($old !== null && array_key_exists('stock',$row)) {
                $expected = $this->integer($row['stock_attendu'] ?? null,0,4294967295);
                if ($expected !== (int)$old['stock']) throw new DomainException('Le stock a changé. Rechargez la variante avant de le modifier.',409);
                $stock = $this->integer($row['stock'],0,4294967295);
                // Garder aussi la place des restitutions S1.7/S1.9 encore possibles.
                $pending = $this->rows("SELECT COALESCE(SUM(l.quantite),0) AS quantite FROM ligne_commande l JOIN commande c ON c.id_commande=l.id_commande WHERE l.id_variante=? AND c.stock_restitue_at IS NULL AND c.statut IN ('en_attente_paiement','confirmee','en_preparation')",[$id])[0]['quantite'];
                if ($stock + (int)$pending > 4294967295) $this->invalid('Ce stock ne laisse pas la place nécessaire aux réservations pouvant être restituées.');
            }
            $status = $row['statut'] ?? ($old['statut'] ?? 'actif');
            if (!in_array($status,['actif','inactif'],true)) $this->invalid('Choisissez un statut de variante valide.');
            // Une variante simple historique conserve sa description libre.
            if ($options === []) $description = $old['description_variante'] ?? null;
            $prepared[] = compact('id','sku','reference','stock','status','key','description','values','reservation');
        }
        if (count($existing)+$newCount > 100) $this->invalid('Un produit ne peut pas dépasser 100 variantes, actives et inactives comprises.');
        if ($options === [] && count($existing)+$newCount !== 1) $this->invalid('Un produit simple doit conserver une seule variante technique. Définissez explicitement ses options.');
        // Aucun historique NULL ne doit rester ambigu au milieu d'un produit à options.
        if ($options !== []) foreach ($existing as $id => $old) {
            if ($old['cle_combinaison'] === null && !isset($seen[$id])) $this->invalid('Classez explicitement toutes les variantes historiques de ce produit.');
        }
        // Réserver tout le lot AVANT les bind métier. Un bind modifie les index du
        // registre sur la connexion métier : alterner bind et nouvelle réservation
        // peut bloquer l'allocateur indépendant sur un gap InnoDB (lots importants).
        foreach ($prepared as &$row) {
            $old=$row['id']===null?null:$existing[$row['id']];
            if ($old===null || $row['sku']!==$old['sku']) {
                if ($this->skuOperation===null) throw new \LogicException('Opération SKU requise.');
                $row['reservation']=$this->skus->reserve($this->skuOperation,(string)$this->skuItem++,$row['sku']);
                $row['sku']=$row['reservation']['sku'];
            }
        }
        unset($row);
        $ids = [];
        foreach ($prepared as $row) {
            if ($row['id'] === null) {
                $id=$this->insertVariant($productId,$row['sku'],$row['reference'],$row['stock'],$row['status'],$row['description'],$row['key']);
            } else {
                $id = $row['id'];
                $this->execute("UPDATE variante_produit SET sku=?,prix_reference=?,devise_reference='USD',stock=?,statut=?,description_variante=?,cle_combinaison=? WHERE id_variante=? AND id_produit=?",[$row['sku'],$row['reference'],$row['stock'],$row['status'],$row['description'],$row['key'],$id,$productId]);
            }
            if ($row['reservation']!==null) $this->skus->bind($row['reservation']['cle'],$id);
            $this->execute('DELETE FROM variante_valeur_option WHERE id_variante=?',[$id]);
            foreach ($row['values'] as $option => $value) $this->execute('INSERT INTO variante_valeur_option(id_variante,id_produit,id_option,id_valeur) VALUES(?,?,?,?)',[$id,$productId,$option,$value]);
            $ids[] = $id;
        }
        return $ids;
    }

    private function insertVariant(int $productId,string $sku,string $price,int $stock,string $status,?string $description,?string $key): int
    {
        $this->execute("INSERT INTO variante_produit(id_produit,sku,prix_reference,devise_reference,stock,statut,description_variante,cle_combinaison) VALUES(?,?,?,'USD',?,?,?,?)",[$productId,$sku,$price,$stock,$status,$description,$key]);
        return (int)$this->database->lastInsertId();
    }

    public function combination(array $options, array $values): array
    {
        $normalized = [];
        foreach ($values as $option => $value) $normalized[$this->integer($option,1,PHP_INT_MAX)] = $this->integer($value,1,PHP_INT_MAX);
        $expected = array_keys($options); $actual = array_keys($normalized); sort($expected,SORT_NUMERIC); sort($actual,SORT_NUMERIC);
        if ($expected !== $actual) $this->invalid('La combinaison doit renseigner exactement toutes les options du produit.');
        if ($options === []) return ['simple',null];
        $parts = []; ksort($normalized,SORT_NUMERIC);
        foreach ($normalized as $option => $value) {
            if (!isset($options[$option]['values'][$value])) $this->invalid('Cette valeur n’appartient pas à l’option du produit.');
            $parts[] = $option.':'.$value;
        }
        $labels = [];
        foreach ($options as $option => $definition) $labels[] = $definition['values'][$normalized[$option]];
        $description = implode(' / ',$labels);
        if (mb_strlen($description) > 255) $this->invalid('Le libellé combiné dépasse 255 caractères. Raccourcissez les valeurs.');
        return [implode('|',$parts),$description];
    }

    public function options(int $productId): array
    {
        $options = [];
        foreach ($this->rows('SELECT id_option FROM option_produit WHERE id_produit=? ORDER BY position,id_option',[$productId]) as $row) $options[(int)$row['id_option']] = ['values'=>[]];
        foreach ($this->rows('SELECT v.id_option,v.id_valeur,v.libelle FROM valeur_option_produit v JOIN option_produit o ON o.id_option=v.id_option WHERE o.id_produit=?',[$productId]) as $row) $options[(int)$row['id_option']]['values'][(int)$row['id_valeur']] = $row['libelle'];
        return $options;
    }

    public function associations(int $id): array
    {
        $values = [];
        foreach ($this->rows('SELECT id_option,id_valeur FROM variante_valeur_option WHERE id_variante=? ORDER BY id_option',[$id]) as $row) $values[(int)$row['id_option']] = (int)$row['id_valeur'];
        return $values;
    }

    public function lockedVariants(int $productId): array
    {
        $rows = [];
        foreach ($this->rows('SELECT * FROM variante_produit WHERE id_produit=? ORDER BY id_variante FOR UPDATE',[$productId]) as $row) $rows[(int)$row['id_variante']] = $row;
        return $rows;
    }

    private function price(array $row): string { return $this->input->price($this->text($row['prix'] ?? null),$this->text($row['devise'] ?? null)); }

    private function text(mixed $value): string { if (!is_string($value)) $this->invalid('Texte invalide.'); return $value; }

    public function label(mixed $value): string
    {
        $value = $this->text($value);
        if (!mb_check_encoding($value,'UTF-8') || preg_match('/[\x00-\x1F\x7F]/u',$value)) $this->invalid('Libellé invalide.');
        $value = trim((string)preg_replace('/[\p{Z}\s]+/u',' ',$value));
        if ($value === '' || mb_strlen($value) > 100) $this->invalid('Indiquez un libellé de 1 à 100 caractères.');
        return $value;
    }

    public function integer(mixed $value, int $min, int $max): int
    {
        if ((!is_int($value) && !is_string($value)) || !preg_match('/^(0|[1-9][0-9]*)$/D',(string)$value) || filter_var($value,FILTER_VALIDATE_INT) === false || (int)$value < $min || (int)$value > $max) $this->invalid('Entier hors limites.');
        return (int)$value;
    }

    public function fields(array $row, array $allowed): void { if (array_diff(array_keys($row),$allowed)) $this->invalid('Champ non autorisé. Les clés, taux et descriptions sont calculés côté serveur.'); }

    private function invalid(string $message): never { throw new DomainException($message,422); }

    private function execute(string $sql, array $params): void { $query=$this->database->prepare($sql);$query->execute($params); }

    private function rows(string $sql, array $params): array { $query=$this->database->prepare($sql);$query->execute($params);return $query->fetchAll(PDO::FETCH_ASSOC); }

}
