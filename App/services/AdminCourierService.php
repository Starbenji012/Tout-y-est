<?php
declare(strict_types=1);
namespace App\Services;
use App\Models\Courier;
use DomainException;
use PDO;
use Throwable;

/** Disponibilité administrative ; la file d'affectation partage la même transaction. */
final class AdminCourierService
{
    private readonly Courier $couriers;
    public function __construct(private readonly PDO $database,private readonly AdminAccessService $access)
    {
        $this->couriers=new Courier($database);
    }
    public function authorize(): int {return $this->access->requireCurrentAdmin();}
    public function listing(): array
    {
        $actor=$this->authorize();$installed=$this->couriers->isReady();
        $tours=new CourierTourService($this->database);$tourReady=$tours->installed();
        $accounts=$tourReady?$this->database->query("SELECT id_utilisateur,nom,prenom,email FROM utilisateur WHERE statut IN ('actif','active') ORDER BY nom,prenom,id_utilisateur")->fetchAll():[];
        $couriers=$installed?$this->couriers->listing():[];$courierAccess=false;
        foreach($couriers as $courier)if($tourReady&&(int)($courier['id_utilisateur']??0)===$actor&&!empty($courier['compte_actif']))$courierAccess=true;
        return ['installed'=>$installed,'couriers'=>$couriers,'tourReady'=>$tourReady,'courierAccounts'=>$accounts,'courierAccess'=>$courierAccess];
    }
    public function save(?int $id,string $name): array
    {
        return $this->mutate(function()use($id,$name):array {
            $name=Courier::cleanName($name);
            if($id===null)return ['id'=>$this->couriers->create($name),'message'=>'Livreur ajouté. Il est indisponible par défaut.'];
            $this->existing($id);$this->couriers->rename($id,$name);
            return ['id'=>$id,'message'=>'Nom du livreur mis à jour. Les anciennes livraisons restent inchangées.'];
        });
    }
    public function setAvailability(int $id,string $status): array
    {
        return $this->mutate(function()use($id,$status):array {
            if(!in_array($status,['actif','inactif'],true))throw new DomainException('Choisissez une disponibilité valide.',422);
            $this->existing($id);$this->couriers->setAvailability($id,$status);
            if ($status==='actif') (new CourierAssignmentService($this->database))->dispatch($this->authorize());
            foreach($this->couriers->listing() as $courier)if((int)$courier['id_livreur']===$id){
                if(!empty($courier['en_course']))return ['id'=>$id,'message'=>$status==='actif'?'Disponibilité activée. Le livreur termine sa course.':'Livreur indisponible pour les prochaines affectations. Sa course actuelle continue.'];
                if(!empty($courier['affecte']))return ['id'=>$id,'message'=>$status==='actif'?'Livreur affecté — départ en attente.':'Livreur indisponible. Son affectation est conservée ; réaffectez explicitement la commande avant le départ.'];
                if($status==='actif'&&array_key_exists('compte_actif',$courier)&&empty($courier['compte_actif']))return ['id'=>$id,'message'=>'Disponibilité administrative activée. Associez un compte actif pour permettre les affectations.'];
            }
            return ['id'=>$id,'message'=>$status==='actif'?'Livreur désormais disponible.':'Livreur désormais indisponible.'];
        });
    }
    private function existing(int $id): array
    {
        if($id<1)throw new DomainException('Livreur invalide.',422);
        return $this->couriers->lock($id)??throw new DomainException('Ce livreur est introuvable.',404);
    }
    private function mutate(callable $operation): array
    {
        $this->database->beginTransaction();
        try {
            $this->access->requireCurrentAdmin(true);
            if(!$this->couriers->isReady())throw new DomainException('La gestion des livreurs n’est pas encore disponible.',503);
            $dispatch=new CourierAssignmentService($this->database);$dispatch->lock();
            $result=$operation();$this->database->commit();return $result;
        }catch(Throwable $error){if($this->database->inTransaction())$this->database->rollBack();throw $error;}
    }
}
