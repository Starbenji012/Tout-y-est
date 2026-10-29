<?php
declare(strict_types=1);
// Compteur PDO simulé : garantit le nombre d'allers-retours sans connexion réelle.
$root=dirname(__DIR__,2);
spl_autoload_register(static function(string $class)use($root):void{if(!str_starts_with($class,'App\\'))return;$parts=explode('\\',substr($class,4));$parts[0]=strtolower($parts[0]);$file=$root.'/App/'.implode('/',$parts).'.php';if(is_file($file))require_once $file;});
final class AdminReadCountingStatement extends PDOStatement
{
    public function __construct(private readonly AdminReadCountingPdo $db,private readonly string $sql) {}
    public function bindValue(string|int $param,mixed $value,int $type=PDO::PARAM_STR):bool{return true;}
    public function execute(?array $params=null):bool{$this->db->queries[]=$this->sql;return true;}
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $cursorOrientation=PDO::FETCH_ORI_NEXT,int $cursorOffset=0):mixed{return ['role'=>'admin','statut'=>'actif'];}
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args):array
    {
        if(str_contains($this->sql,'FROM commande c'))return array_map(static fn($id)=>['id_commande'=>$id,'numero_commande'=>'TYE-'.$id,'date_commande'=>'2026-10-24 12:00:00','statut'=>'confirmee','devise_reference'=>'USD','taux_usd_cdf'=>'2450.0000','total'=>'45.00','sous_total'=>'40.00','frais_livraison'=>'5.00'],range(1,$this->db->rows));
        if(str_contains($this->sql,'FROM paiement p'))return [['id_commande'=>1,'id_paiement'=>1,'statut'=>'a_encaisser','mode'=>'Espèces à la livraison','montant'=>'45.00']];
        return [];
    }
}
final class AdminReadCountingPdo extends PDO
{
    public array $queries=[];
    public int $rows=20;
    public function __construct() {}
    public function prepare(string $query,array $options=[]):PDOStatement|false{return new AdminReadCountingStatement($this,$query);}
}
App\Core\Session::start();App\Core\Session::set('user',['id'=>1]);
$db=new AdminReadCountingPdo();
$service=new App\Services\AdminOrderReadService(new App\Services\AdminAccessService(new App\Models\User($db)),new App\Models\AdminOrderRead($db),new App\Models\OrderItem($db),new App\Models\Delivery($db),new App\Models\Payment($db),new App\Models\AdminOrderJournal($db),new App\Services\MoneyService());
foreach([1,20,21]as$count){$db->rows=$count;$db->queries=[];$data=$service->listing(['etat'=>'confirmee','encaisser'=>'1']);if(count($db->queries)!==4)throw new RuntimeException('Régression N+1 : '.count($db->queries).' requêtes');if(count($data['orders'])!==min($count,20)||$data['orders'][0]['paymentStatus']!=='À encaisser'||$data['orders'][0]['cdf']!=='110 250 FC')throw new RuntimeException('Présentation modifiée');}
echo "ADMIN_ORDER_READ_QUERIES_OK (4 requêtes service pour 1/20 cartes, paiements/livraisons groupés, snapshots conservés).\n";
