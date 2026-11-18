<?php
declare(strict_types=1);
namespace App\Services;

use App\Models\{Courier,Order,Payment,Delivery,AdminOrderJournal,PaymentMethod};
use DomainException;
use PDO;

/** Même ordre de verrous pour toute affectation : livreurs, commande, paiement, livraison. */
final class CourierAssignmentService
{
    public function __construct(private readonly PDO $database) {}

    public function lock(): void
    {
        if (!$this->database->inTransaction()) throw new \LogicException('Transaction requise.');
        $tours=new CourierTourService($this->database);
        if($tours->installed()){$tours->lock();return;}
        if((int)$this->database->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='verrou_dispatch'")->fetchColumn())throw new DomainException('La mise à jour S1.15 est incomplète. Intervention requise.',503);
        $ready=(int)$this->database->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()
            AND ((TABLE_NAME='livraison' AND COLUMN_NAME='id_livreur_engage')
              OR (TABLE_NAME='journal_commande' AND COLUMN_NAME='action_unique'))")->fetchColumn();
        if ($ready!==2) throw new DomainException('La mise à jour S1.14 des affectations doit être appliquée avant cette action.',503);
        $indexes=(int)$this->database->query("SELECT COUNT(DISTINCT INDEX_NAME) FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA=DATABASE() AND NON_UNIQUE=0
              AND ((TABLE_NAME='livraison' AND INDEX_NAME='uq_livraison_livreur_engage')
                OR (TABLE_NAME='journal_commande' AND INDEX_NAME='uq_journal_transition'))")->fetchColumn();
        $old=(int)$this->database->query("SELECT COUNT(*) FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='journal_commande' AND INDEX_NAME='uq_journal_commande_action'")->fetchColumn();
        $check=(int)$this->database->query("SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='journal_commande'
                AND CONSTRAINT_NAME='chk_journal_action' AND CHECK_CLAUSE LIKE '%assign%'")->fetchColumn();
        if($indexes!==2||$old!==0||$check!==1)throw new DomainException('La mise à jour S1.14 est incomplète. Faites contrôler les contraintes avant de continuer.',503);
        // V1 : file courte sérialisée, indépendante du nombre d'admins et sûre même avec zéro livreur.
        $this->database->query('SELECT id_livreur FROM livreur ORDER BY id_livreur FOR UPDATE')->fetchAll();
    }

    /** Appel uniquement après lock(), dans la transaction de l'action Admin. */
    public function dispatch(int $actor): void
    {
        $tours=new CourierTourService($this->database);
        if($tours->installed()){$tours->dispatch($actor);return;}
        $capacity=count((new Courier($this->database))->active());
        if($capacity===0)return;
        $ids=$this->database->query("SELECT c.id_commande FROM commande c JOIN livraison l ON l.id_commande=c.id_commande
            WHERE c.statut='en_preparation' AND l.statut='en_preparation' AND l.id_livreur IS NULL
                AND c.reservation_expire_at IS NULL AND c.stock_restitue_at IS NULL
                AND l.date_preparation IS NOT NULL AND l.date_expedition IS NULL AND l.date_livraison IS NULL
                AND (SELECT COUNT(*) FROM paiement p WHERE p.id_commande=c.id_commande)=1
                AND EXISTS(SELECT 1 FROM paiement p JOIN mode_paiement m ON m.id_mode_paiement=p.id_mode_paiement
                    WHERE p.id_commande=c.id_commande AND p.statut='a_encaisser' AND p.date_paiement IS NULL
                        AND m.nom='Espèces à la livraison')
            ORDER BY l.date_preparation,c.date_commande,c.id_commande LIMIT ".$capacity)->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $id) {
            $free=(new Courier($this->database))->active();
            if (!$free) break;
            $this->assign((int)$id,(int)$free[0]['id_livreur'],$actor,null,'automatique');
        }
    }

    /** Attendu protège une décision affichée devenue obsolète, jamais d'écrasement implicite. */
    public function assign(int $id,int $courierId,int $actor,?int $expected,string $origin='administrateur'): bool
    {
        if((new CourierTourService($this->database))->installed())throw new DomainException('Les colis sont affectés automatiquement aux tournées.',409);
        if (!$this->database->inTransaction()) throw new \LogicException('Transaction requise.');
        $order=(new Order($this->database))->find($id,true);
        if (!$order) throw new DomainException('Commande introuvable.',404);
        $payments=(new Payment($this->database))->forOrder($id,true);
        $deliveries=new Delivery($this->database);$delivery=$deliveries->lockForOrder($id);
        if (!$delivery || $order['statut']!=='en_preparation' || $delivery['statut']!=='en_preparation'
            || $order['reservation_expire_at']!==null || $order['stock_restitue_at']!==null
            || $delivery['date_preparation']===null || $delivery['date_expedition']!==null || $delivery['date_livraison']!==null
            || count($payments)!==1 || $payments[0]['mode']!==PaymentMethod::CASH
            || $payments[0]['statut']!=='a_encaisser' || $payments[0]['date_paiement']!==null)
            throw new DomainException('Cette commande ne peut plus être affectée.',409);
        $old=isset($delivery['id_livreur'])?(int)$delivery['id_livreur']:null;
        if ($old===$courierId) return false; // Retry : ne pas réécrire le nom historique.
        if ($old!==$expected) throw new DomainException('Le livreur a changé. Rechargez la commande avant de confirmer.',409);
        $courier=null;
        foreach ((new Courier($this->database))->active($id) as $candidate) {
            if ((int)$candidate['id_livreur']===$courierId) {$courier=$candidate;break;}
        }
        if (!$courier) throw new DomainException('Ce livreur est indisponible ou déjà affecté.',422);
        $name=Courier::cleanName($courier['nom']);
        $deliveries->assignCourier($id,$courierId,$name);
        (new AdminOrderJournal($this->database))->append($id,$actor,(int)$payments[0]['id_paiement'],'assign',
            ['id_livreur'=>$old,'livreur'=>$delivery['nom_livreur_snapshot']],
            ['id_livreur'=>$courierId,'livreur'=>$name],
            ['ancien_livreur'=>$delivery['nom_livreur_snapshot'],'ancien_id_livreur'=>$old,
             'livreur'=>$name,'id_livreur'=>$courierId,'origine'=>$origin]);
        return true;
    }
}
