<?php
declare(strict_types=1);
namespace App\Services;

use DomainException;
use PDO;
use PDOException;
use Throwable;

require_once __DIR__.'/CatalogueWriteLock.php';
require_once __DIR__.'/ProductFormValidationException.php';

/** Les réservations survivent aux rollbacks métier. Aucune référence n'est libérée. */
final class SkuService
{
    private ?PDO $allocator = null;

    public function __construct(private readonly PDO $business) {}

    private function allocator(): PDO
    {
        if ($this->allocator!==null) return $this->allocator;
        $config=require dirname(__DIR__,2).'/Config/database.php';
        // Important pour les bases jetables et les connexions ayant exécuté USE.
        $database=(string)$this->business->query('SELECT DATABASE()')->fetchColumn();
        if ($database==='') throw new DomainException('Base catalogue indisponible.',503);
        $db=new PDO('mysql:host='.$config['host'].';port='.$config['port'].';dbname='.$database.';charset=utf8mb4',$config['username'],$config['password'],$config['options']);
        $source=$this->business->query('SELECT @@server_id,@@hostname,@@port')->fetch(PDO::FETCH_NUM);
        if ($source!==$db->query('SELECT @@server_id,@@hostname,@@port')->fetch(PDO::FETCH_NUM)) throw new DomainException('Connexion SKU incompatible avec le catalogue.',503);
        return $this->allocator=$db;
    }

    public static function format(string $number): string
    {
        if (!preg_match('/^[1-9][0-9]{0,19}$/D',$number) || (strlen($number)===20 && strcmp($number,'18446744073709551615')>0)) throw new DomainException('Compteur SKU hors limites.',503);
        return 'TYE-P'.str_pad($number,6,'0',STR_PAD_LEFT);
    }

    /** Une clé est propre à un administrateur, un parcours et une requête logique. */
    public function operation(string $scope, ?string $request, array $payload): string
    {
        $request??=bin2hex(random_bytes(16));
        if (!preg_match('/^[a-f0-9]{32}$/D',$request)) throw new DomainException('Clé de création invalide. Rechargez le formulaire.',422);
        $actor=\App\Core\Session::get('user',[])['id']??0;
        $key=hash('sha256',$actor.':'.$scope.':'.$request);
        $hash=hash('sha256',json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
        $db=$this->allocator();
        // INSERT IGNORE ne doit jamais masquer une erreur de schéma ou de troncature.
        try {
            $q=$db->prepare("INSERT INTO sku_attribution(cle,origine,empreinte) VALUES(?,'operation',?)");
            $q->execute([$key,$hash]);
        } catch(PDOException $e) { if ((int)($e->errorInfo[1]??0)!==1062) throw $e; }
        $q=$db->prepare('SELECT empreinte FROM sku_attribution WHERE cle=?');$q->execute([$key]);
        if ($q->fetchColumn()!==$hash) throw new DomainException('Cette clé de création correspond à une autre saisie. Rechargez le formulaire.',409);
        return $key;
    }

    /** À appeler dans la transaction métier, avant toute mutation. */
    public function replay(string $operation): ?array
    {
        if (!$this->business->inTransaction()) throw new \LogicException('Transaction métier requise.');
        $q=$this->business->prepare('SELECT resultat FROM sku_attribution WHERE cle=? AND origine=\'operation\' FOR UPDATE');$q->execute([$operation]);
        $value=$q->fetchColumn();
        if ($value===false) throw new \LogicException('Opération SKU absente.');
        return $value===null?null:json_decode($value,true,512,JSON_THROW_ON_ERROR);
    }

    public function complete(string $operation, array $result): void
    {
        $q=$this->business->prepare('UPDATE sku_attribution SET resultat=? WHERE cle=? AND resultat IS NULL');
        $q->execute([json_encode($result,JSON_THROW_ON_ERROR),$operation]);
        if ($q->rowCount()!==1) throw new \LogicException('Résultat SKU déjà enregistré.');
    }

    public function reserve(string $operation, string $item, string $manual = ''): array
    {
        $manual=strtoupper(trim($manual));
        if ($manual!==''&&!preg_match('/^[A-Z0-9][A-Z0-9._-]{0,99}$/D',$manual)) throw new ProductFormValidationException('sku','Référence SKU invalide (100 caractères maximum).');
        $key=hash('sha256',$operation.':'.$item);
        $hash=hash('sha256',$manual);
        $db=$this->allocator();
        $db->beginTransaction();
        try {
            // Ordre de verrouillage identique pour les réservations manuelles et auto.
            $counter=$db->query('SELECT dernier_numero FROM sku_sequence WHERE id=1 FOR UPDATE')->fetchColumn();
            if ($counter===false) throw new DomainException('Migration SKU requise.',503);
            $q=$db->prepare('SELECT sku,empreinte FROM sku_attribution WHERE cle=?');$q->execute([$key]);$old=$q->fetch();
            if ($old) {
                if ($old['empreinte']!==$hash) throw new DomainException('Réservation SKU incompatible.',409);
                $db->commit();return ['cle'=>$key,'sku'=>$old['sku']];
            }
            $sku=$manual;
            if ($sku==='') {
                do {
                    if ((string)$counter==='18446744073709551615') throw new DomainException('Compteur SKU épuisé.',503);
                    $db->exec('UPDATE sku_sequence SET dernier_numero=dernier_numero+1 WHERE id=1');
                    $counter=(string)$db->query('SELECT dernier_numero FROM sku_sequence WHERE id=1')->fetchColumn();
                    $sku=self::format($counter);
                    $q=$db->prepare('SELECT 1 FROM sku_attribution WHERE sku=? UNION ALL SELECT 1 FROM variante_produit WHERE sku=? LIMIT 1');$q->execute([$sku,$sku]);
                } while ($q->fetchColumn()!==false);
            }
            // Le catalogue couvre aussi un éventuel import externe non encore enregistré.
            $q=$db->prepare('SELECT 1 FROM variante_produit WHERE sku=? LIMIT 1');$q->execute([$sku]);
            if ($q->fetchColumn()!==false) throw new ProductFormValidationException('sku','Cette référence (SKU) est déjà utilisée.');
            $q=$db->prepare('INSERT INTO sku_attribution(cle,sku,origine,empreinte) VALUES(?,?,?,?)');
            $q->execute([$key,$sku,$manual===''?'automatique':'manuel',$hash]);
            $db->commit();return ['cle'=>$key,'sku'=>$sku];
        } catch(Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            if ($e instanceof PDOException && (int)($e->errorInfo[1]??0)===1062) throw new ProductFormValidationException('sku','Cette référence (SKU) a déjà été attribuée et ne peut pas être réutilisée.');
            throw $e;
        }
    }

    /** Le rattachement est atomique avec la variante ; il n'efface jamais la réservation. */
    public function bind(string $key, int $variant): void
    {
        $q=$this->business->prepare('UPDATE sku_attribution SET id_variante=? WHERE cle=? AND (id_variante IS NULL OR id_variante=?)');$q->execute([$variant,$key,$variant]);
        $q=$this->business->prepare('SELECT id_variante FROM sku_attribution WHERE cle=?');$q->execute([$key]);
        if ((int)$q->fetchColumn()!==$variant) throw new DomainException('Référence déjà rattachée à une autre variante.',409);
    }
}
