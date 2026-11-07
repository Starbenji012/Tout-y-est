<?php
declare(strict_types=1);
namespace App\Services;
use App\Models\Courier;
use DomainException;
use PDO;
use Throwable;

/** Gestion du référentiel seulement : aucune modification de livraison ou de commande. */
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
        $this->authorize();$installed=$this->couriers->isReady();
        return ['installed'=>$installed,'couriers'=>$installed?$this->couriers->listing():[]];
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
            $result=$operation();$this->database->commit();return $result;
        }catch(Throwable $error){if($this->database->inTransaction())$this->database->rollBack();throw $error;}
    }
}
