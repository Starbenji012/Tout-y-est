<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Session;
use DomainException;
use PDO;
use Throwable;

/** Un verrou court commun protège affectation, manifeste, retour et cash. Aucun accès réseau sous verrou. */
final class CourierTourService
{
    public function __construct(private readonly PDO $db) {}

    public function installed(): bool
    {
        if((int)$this->db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='verrou_dispatch'")->fetchColumn()!==1)return false;
        // Dans une transaction, lecture courante/verrouillée AVANT toute vue répétable :
        // attendre le verrou ne doit pas laisser un ancien instantané du manifeste.
        $lock=$this->db->inTransaction()?' FOR UPDATE':'';
        return (int)$this->db->query('SELECT version_schema FROM verrou_dispatch WHERE id=1'.$lock)->fetchColumn()===15;
    }

    public function lock(): void
    {
        if (!$this->db->inTransaction()) throw new \LogicException('Transaction requise.');
        if (!$this->installed()) throw new DomainException('La mise à jour S1.15 doit être appliquée.',503);
        $this->db->query('SELECT id FROM verrou_dispatch WHERE id=1 FOR UPDATE')->fetchColumn();
    }

    /** L’identité vient exclusivement de la session ; inactif administrativement peut terminer sa tournée. */
    public function identity(): array
    {
        $user=Session::get('user'); $id=is_array($user)?(int)($user['id']??0):0;
        if ($id<1) throw new DomainException('Connectez-vous.',401);
        $this->requireInstalled();
        $q=$this->db->prepare("SELECT l.* FROM livreur l JOIN utilisateur u ON u.id_utilisateur=l.id_utilisateur
            WHERE u.id_utilisateur=? AND u.statut IN ('actif','active')");
        $q->execute([$id]);
        return $q->fetch()?:throw new DomainException('Accès réservé à votre espace livreur.',403);
    }

    /** Appel sous verrou, après préparation ou disponibilité/retour. Jamais depuis un GET. */
    public function dispatch(?int $actor): void
    {
        if (!$this->db->inTransaction()) throw new \LogicException('Transaction requise.');
        $pending=$this->db->query("SELECT c.id_commande,l.id_livraison FROM commande c JOIN livraison l ON l.id_commande=c.id_commande
            WHERE c.statut='en_preparation' AND l.statut='en_preparation' AND l.id_livreur IS NULL
            AND c.stock_restitue_at IS NULL AND c.reservation_expire_at IS NULL
            AND l.date_preparation IS NOT NULL AND l.date_expedition IS NULL AND l.date_livraison IS NULL
            AND NOT EXISTS(SELECT 1 FROM tentative_livraison a WHERE a.id_livraison=l.id_livraison)
            AND (SELECT COUNT(*) FROM paiement p WHERE p.id_commande=c.id_commande)=1
            AND EXISTS(SELECT 1 FROM paiement p JOIN mode_paiement m ON m.id_mode_paiement=p.id_mode_paiement
                WHERE p.id_commande=c.id_commande AND p.statut='a_encaisser' AND p.date_paiement IS NULL AND m.nom='Espèces à la livraison')
            ORDER BY l.date_preparation,c.id_commande FOR UPDATE")->fetchAll();
        foreach ($pending as $row) {
            // Les tournées en chargement ont priorité ; parmi elles, la moins chargée puis le plus petit ID.
            $candidate=$this->db->query("SELECT l.id_livreur,l.nom,t.id_tournee,
                (SELECT COUNT(*) FROM tentative_livraison a WHERE a.id_tournee=t.id_tournee) AS charge_colis
                FROM livreur l JOIN utilisateur u ON u.id_utilisateur=l.id_utilisateur AND u.statut IN ('actif','active')
                LEFT JOIN tournee t ON t.livreur_ouvert=l.id_livreur
                WHERE l.statut='actif' AND (t.id_tournee IS NULL OR t.statut='chargement')
                HAVING charge_colis<5 ORDER BY (id_tournee IS NULL),charge_colis,l.id_livreur LIMIT 1")->fetch();
            if (!$candidate) break;
            $tour=(int)($candidate['id_tournee']??0);
            if (!$tour) {
                $q=$this->db->prepare('INSERT INTO tournee(id_livreur,nom_livreur_snapshot) VALUES(?,?)');
                $q->execute([$candidate['id_livreur'],$candidate['nom']]); $tour=(int)$this->db->lastInsertId();
            }
            $q=$this->db->prepare('INSERT INTO tentative_livraison(id_tournee,id_livraison,emplacement) VALUES(?,?,?)');
            $q->execute([$tour,$row['id_livraison'],(int)$candidate['charge_colis']+1]); $attempt=(int)$this->db->lastInsertId();
            $this->execute('UPDATE tournee SET version_manifeste=version_manifeste+1 WHERE id_tournee=?',[$tour]);
            $this->execute('UPDATE livraison SET id_livreur=?,nom_livreur_snapshot=? WHERE id_livraison=?',[$candidate['id_livreur'],$candidate['nom'],$row['id_livraison']]);
            $this->event((int)$row['id_commande'],'assign','tentative:'.$attempt,$actor?'admin':'systeme',$actor,
                ['id_livreur'=>(int)$candidate['id_livreur'],'livreur'=>$candidate['nom'],'tournee'=>$tour]);
        }
    }

    /** Une seule action Admin après la préparation physique. */
    public function ready(int $orderId, AdminAccessService $access): array
    {
        return $this->transaction(function()use($orderId,$access):array {
            $actor=$access->requireCurrentAdmin(true);
            $q=$this->db->prepare('SELECT c.*,l.id_livraison,l.statut AS livraison_statut FROM commande c JOIN livraison l ON l.id_commande=c.id_commande WHERE c.id_commande=? FOR UPDATE');
            $q->execute([$orderId]); $row=$q->fetch()?:throw new DomainException('Commande introuvable.',404);
            $q=$this->db->prepare("SELECT id_journal FROM journal_commande WHERE id_commande=? AND action='prepare'");$q->execute([$orderId]);
            if($q->fetchColumn()) {
                if(!in_array($row['statut'],['en_preparation','en_livraison','livree'],true))$this->conflict();
                return ['success'=>true,'reference'=>$row['numero_commande'],'commande'=>$orderId,'reused'=>true,'message'=>'Cette commande est déjà prête.'];
            }
            $q=$this->db->prepare('SELECT p.*,m.nom AS mode FROM paiement p JOIN mode_paiement m ON m.id_mode_paiement=p.id_mode_paiement WHERE p.id_commande=? FOR UPDATE');
            $q->execute([$orderId]);$payments=$q->fetchAll();
            if(count($payments)!==1||$payments[0]['mode']!=='Espèces à la livraison'||$payments[0]['statut']!=='a_encaisser'||$payments[0]['date_paiement']!==null||$row['stock_restitue_at']!==null||$row['reservation_expire_at']!==null) $this->conflict();
            $reused=false;
            if (!$reused) {
                if($row['statut']!=='confirmee'||$row['livraison_statut']!=='en_attente') $this->conflict();
                $this->execute("UPDATE commande SET statut='en_preparation',date_modification=NOW() WHERE id_commande=?",[$orderId]);
                $this->execute("UPDATE livraison SET statut='en_preparation',date_preparation=NOW() WHERE id_commande=?",[$orderId]);
                $this->event($orderId,'prepare','commande:'.$orderId,'admin',$actor,[]);
            }
            $this->dispatch($actor);
            return ['success'=>true,'reference'=>$row['numero_commande'],'commande'=>$orderId,'reused'=>$reused,'message'=>'Commande prête. Affectation automatique effectuée si un livreur est disponible.'];
        });
    }

    public function start(int $tourId,int $version): void
    {
        $this->transaction(function()use($tourId,$version):void {
            $courier=$this->identity();$tour=$this->ownedTour($tourId,(int)$courier['id_livreur']);
            if((int)$tour['version_manifeste']!==$version) throw new DomainException('Le lot de colis a changé. Rechargez et vérifiez les colis avant le départ.',409);
            if($tour['statut']==='en_cours') return;
            if($tour['statut']!=='chargement'||$courier['statut']!=='actif') $this->conflict();
            $rows=$this->manifest($tourId);if(!$rows)$this->conflict();
            foreach($rows as $row) {
                if($row['resultat']==='annulee')continue;
                if($row['resultat']!=='en_attente'||$row['commande_statut']!=='en_preparation'||$row['livraison_statut']!=='en_preparation'
                    ||$row['stock_restitue_at']!==null||$row['reservation_expire_at']!==null)$this->conflict();
                $this->execute("UPDATE commande SET statut='en_livraison',date_modification=NOW() WHERE id_commande=?",[$row['id_commande']]);
                $this->execute("UPDATE livraison SET statut='en_livraison',date_expedition=NOW() WHERE id_livraison=?",[$row['id_livraison']]);
                $this->event((int)$row['id_commande'],'depart','tentative:'.$row['id_tentative'],'livreur',(int)$courier['id_utilisateur'],['tournee'=>$tourId]);
            }
            $this->execute("UPDATE tournee SET statut='en_cours',date_depart=NOW() WHERE id_tournee=?",[$tourId]);
        });
    }

    public function outcome(int $attemptId,string $result,string $reason='',string $comment=''): void
    {
        $comment=trim($comment);
        $length=function_exists('mb_strlen')?mb_strlen($comment):preg_match_all('/./us',$comment);
        if(!in_array($result,['livree','probleme'],true)||!preg_match('//u',$comment)||$length>500||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$comment))throw new DomainException('Résultat invalide.',422);
        if($result==='probleme'&&(!in_array($reason,['absent','injoignable','adresse','refus','paiement','autre'],true)||($reason==='autre'&&$comment==='')))throw new DomainException('Précisez le motif du problème.',422);
        $this->transaction(function()use($attemptId,$result,$reason,$comment):void {
            $courier=$this->identity();
            $q=$this->db->prepare('SELECT a.* FROM tentative_livraison a JOIN tournee t ON t.id_tournee=a.id_tournee WHERE a.id_tentative=? AND t.id_livreur=? FOR UPDATE');
            $q->execute([$attemptId,$courier['id_livreur']]);$attempt=$q->fetch()?:throw new DomainException('Livraison introuvable.',404);
            $tour=$this->ownedTour((int)$attempt['id_tournee'],(int)$courier['id_livreur']);
            if($attempt['resultat']===$result) {
                if($result==='probleme'&&($attempt['motif']!==$reason||($attempt['commentaire']??'')!==$comment))$this->conflict();
                return; // Le retry ne change ni date, ni cash, ni journal, même après retour.
            }
            if($tour['statut']!=='en_cours'||$attempt['resultat']!=='en_attente')$this->conflict();
            $q=$this->db->prepare('SELECT * FROM livraison WHERE id_livraison=? FOR UPDATE');$q->execute([$attempt['id_livraison']]);$delivery=$q->fetch();$order=(int)$delivery['id_commande'];
            $payment=$this->cash($order);
            if($delivery['statut']!=='en_livraison'||$payment['commande_statut']!=='en_livraison'||$payment['stock_restitue_at']!==null||$payment['reservation_expire_at']!==null)$this->conflict();
            $this->execute('UPDATE tentative_livraison SET resultat=?,motif=?,commentaire=?,date_resultat=NOW() WHERE id_tentative=?',[$result,$result==='probleme'?$reason:null,$comment?:null,$attemptId]);
            if($result==='livree') {
                if($payment['statut']!=='a_encaisser')$this->conflict();
                [$currency,$amount]=$this->cashAmount($payment);
                $this->execute('INSERT INTO collecte_cash(id_paiement,id_tentative,id_livreur,montant,devise) VALUES(?,?,?,?,?)',[$payment['id_paiement'],$attemptId,$courier['id_livreur'],$amount,$currency]);
                $this->execute("UPDATE paiement SET statut='collecte_livreur',date_modification=NOW() WHERE id_paiement=?",[$payment['id_paiement']]);
                $this->execute("UPDATE commande SET statut='livree',date_modification=NOW() WHERE id_commande=?",[$order]);
                $this->execute("UPDATE livraison SET statut='livree',date_livraison=NOW() WHERE id_livraison=?",[$attempt['id_livraison']]);
                $this->event($order,'cash-collected','paiement:'.$payment['id_paiement'],'livreur',(int)$courier['id_utilisateur'],['montant'=>$amount,'devise'=>$currency,'tournee'=>(int)$tour['id_tournee']]);
            }
            $this->event($order,$result==='livree'?'deliver':'incident','tentative:'.$attemptId,'livreur',(int)$courier['id_utilisateur'],['motif'=>$reason,'commentaire'=>$comment]);
        });
    }

    public function returned(int $tourId): void
    {
        $this->transaction(function()use($tourId):void {
            $courier=$this->identity();$tour=$this->ownedTour($tourId,(int)$courier['id_livreur']);
            if($tour['statut']==='retour_termine')return;
            if($tour['statut']!=='en_cours')$this->conflict();
            $rows=$this->manifest($tourId);
            foreach($rows as $row)if($row['resultat']==='en_attente')throw new DomainException('Renseignez chaque livraison ou problème avant votre retour.',409);
            foreach($rows as $row) {
                if($row['resultat']==='annulee')continue;
                $this->execute('UPDATE tentative_livraison SET date_cloture=NOW() WHERE id_tentative=?',[$row['id_tentative']]);
                $this->event((int)$row['id_commande'],'return','tentative:'.$row['id_tentative'],'livreur',(int)$courier['id_utilisateur'],['resultat'=>$row['resultat']]);
            }
            // Un problème reste à traiter humainement. Ni remise en stock ni nouvelle présentation automatique.
            $this->execute("UPDATE tournee SET statut='retour_termine',date_retour=NOW() WHERE id_tournee=?",[$tourId]);
            $this->dispatch(null);
        });
    }

    /** Une annulation autorisée avant départ libère l'affectation, sans simuler un retour physique. */
    public function cancelledAssignment(int $orderId): void
    {
        if(!$this->db->inTransaction())throw new \LogicException('Transaction requise.');
        $q=$this->db->prepare('SELECT a.*,t.statut AS tour_statut FROM tentative_livraison a JOIN livraison l ON l.id_livraison=a.id_livraison JOIN tournee t ON t.id_tournee=a.id_tournee WHERE l.id_commande=? AND a.date_cloture IS NULL FOR UPDATE');
        $q->execute([$orderId]);$row=$q->fetch();if(!$row)return;
        if($row['tour_statut']!=='chargement')$this->conflict();
        $this->execute("UPDATE tentative_livraison SET resultat='annulee',date_resultat=NOW(),date_cloture=NOW() WHERE id_tentative=?",[$row['id_tentative']]);
        $this->execute('UPDATE tournee SET version_manifeste=version_manifeste+1 WHERE id_tournee=?',[$row['id_tournee']]);
        $q=$this->db->prepare("SELECT COUNT(*) FROM tentative_livraison WHERE id_tournee=? AND resultat<>'annulee'");$q->execute([$row['id_tournee']]);
        if((int)$q->fetchColumn()===0)$this->execute("UPDATE tournee SET statut='annulee' WHERE id_tournee=?",[$row['id_tournee']]);
    }

    /** Le gérant confirme la somme physiquement reçue, séparément par devise. */
    public function remit(int $tourId,array $amounts,AdminAccessService $access,bool $exact=false,?string $reason=null): array
    {
        if($exact&&($amounts!==[]||$reason!==null))throw new DomainException('Le montant attendu est calculé par le serveur.',422);
        return $this->transaction(function()use($tourId,$amounts,$access,$exact,$reason):array {
            $actor=$access->requireCurrentAdmin(true);
            $q=$this->db->prepare("SELECT * FROM tournee WHERE id_tournee=? AND statut='retour_termine' FOR UPDATE");$q->execute([$tourId]);$tour=$q->fetch();if(!$tour)$this->conflict();
            $q=$this->db->prepare('SELECT k.*,l.id_commande FROM collecte_cash k JOIN tentative_livraison a ON a.id_tentative=k.id_tentative JOIN livraison l ON l.id_livraison=a.id_livraison WHERE a.id_tournee=? ORDER BY k.id_collecte FOR UPDATE');$q->execute([$tourId]);$rows=$q->fetchAll();
            if(!$rows)$this->conflict();$totals=[];
            foreach($rows as $row)$totals[$row['devise']]=($totals[$row['devise']]??0)+$this->minor($row['montant']);
            // Le bouton de confirmation ne fournit aucun montant : on le relit sous verrou.
            if($exact)$amounts=array_map(static fn(int $n):string=>intdiv($n,100).'.'.str_pad((string)($n%100),2,'0',STR_PAD_LEFT),$totals);
            if(array_diff(array_keys($amounts),array_keys($totals))||array_diff(array_keys($totals),array_keys($amounts)))throw new DomainException('Confirmez chaque devise séparément.',422);
            if($reason!==null){
                $reason=trim($reason);
                if(!preg_match('//u',$reason)||preg_match('/[\x00-\x1f\x7f]/u',$reason)||$reason===''||(function_exists('mb_strlen')?mb_strlen($reason):preg_match_all('/./us',$reason))>500)throw new DomainException('Précisez le motif de l’écart (500 caractères maximum).',422);
                $different=false;
                foreach($totals as $currency=>$total){if(!is_string($amounts[$currency]))throw new DomainException('Montant invalide.',422);$different=$this->minor($amounts[$currency])!==$total||$different;}
                if(!$different)throw new DomainException('Les montants correspondent. Utilisez la confirmation normale.',422);
                if(array_filter($rows,static fn(array $row):bool=>$row['date_remise']!==null))$this->conflict();
                $data=['type'=>'ecart_cash','tournee'=>$tourId,'attendu_centimes'=>$totals,'recu'=>$amounts,'motif'=>$reason];
                $resource='ecart-cash:'.$tourId.':'.substr(hash('sha256',json_encode($data,JSON_THROW_ON_ERROR)),0,32);
                foreach(array_unique(array_column($rows,'id_commande')) as $order){
                    $q=$this->db->prepare("SELECT 1 FROM journal_commande WHERE id_commande=? AND action='incident' AND ressource=?");$q->execute([$order,$resource]);
                    if(!$q->fetchColumn())$this->event((int)$order,'incident',$resource,'admin',$actor,$data);
                }
                // Un signalement n'est ni un encaissement ni une validation de remise.
                return ['reported'=>true];
            }
            foreach($totals as $currency=>$total)if(!is_string($amounts[$currency])||$this->minor($amounts[$currency])!==$total)throw new DomainException('Écart de caisse : aucune remise validée. Faites vérifier les montants.',422);
            $reused=true;
            foreach($rows as $row) {
                if($row['date_remise']!==null)continue;
                $reused=false;
                $p=$this->cash((int)$row['id_commande']);if($p['statut']!=='collecte_livreur')$this->conflict();
                $this->execute('UPDATE collecte_cash SET date_remise=NOW(),id_confirmateur=? WHERE id_collecte=?',[$actor,$row['id_collecte']]);
                $this->execute("UPDATE paiement SET statut='paye',date_paiement=NOW(),date_modification=NOW() WHERE id_paiement=?",[$row['id_paiement']]);
                $this->event((int)$row['id_commande'],'cash-remitted','paiement:'.$row['id_paiement'],'admin',$actor,['tournee'=>$tourId,'montant'=>$row['montant'],'devise'=>$row['devise']]);
            }
            return ['courier'=>$tour['nom_livreur_snapshot'],'amounts'=>array_map(static fn(int $n):string=>intdiv($n,100).'.'.str_pad((string)($n%100),2,'0',STR_PAD_LEFT),$totals),'reused'=>$reused];
        });
    }

    /** Deux requêtes métier groupées quel que soit le nombre de colis, plus contrôle d'identité. */
    public function page(): array
    {
        $courier=$this->identity();
        $q=$this->db->prepare('SELECT * FROM tournee WHERE id_livreur=? ORDER BY id_tournee DESC LIMIT 10');$q->execute([$courier['id_livreur']]);$tours=$q->fetchAll();
        $ids=array_column($tours,'id_tournee');$rows=[];
        if($ids){$q=$this->db->prepare($this->manifestSql().' WHERE a.id_tournee IN ('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY a.id_tournee DESC,a.emplacement');$q->execute($ids);$rows=$q->fetchAll();}
        $collectedTotals=[];
        // Résumé d'affichage uniquement : les collectes réelles, séparées par devise.
        foreach($rows as $row)if($row['montant_collecte']!==null){$id=(int)$row['id_tournee'];$currency=$row['devise_collecte'];$collectedTotals[$id][$currency]=($collectedTotals[$id][$currency]??0)+$this->minor($row['montant_collecte']);}
        foreach($collectedTotals as &$totals)foreach($totals as &$minor)$minor=intdiv($minor,100).'.'.str_pad((string)($minor%100),2,'0',STR_PAD_LEFT);unset($totals,$minor);
        return ['courier'=>$courier,'tours'=>$tours,'parcels'=>$rows,'collectedTotals'=>$collectedTotals];
    }

    public function remittances(AdminAccessService $access): array
    {
        $access->requireCurrentAdmin();$this->requireInstalled();
        return $this->db->query("SELECT t.id_tournee,t.nom_livreur_snapshot,k.devise,SUM(k.montant) AS montant,COUNT(*) AS livraisons
            FROM tournee t JOIN tentative_livraison a ON a.id_tournee=t.id_tournee JOIN collecte_cash k ON k.id_tentative=a.id_tentative
            WHERE t.statut='retour_termine' AND k.date_remise IS NULL GROUP BY t.id_tournee,t.nom_livreur_snapshot,k.devise ORDER BY t.id_tournee")->fetchAll();
    }

    /** Association explicite à un compte existant : l'adresse e-mail n'est jamais une preuve d'identité seule. */
    public function link(int $courierId,int $userId,AdminAccessService $access): void
    {
        $this->transaction(function()use($courierId,$userId,$access):void {
            $access->requireCurrentAdmin(true);
            $q=$this->db->prepare("SELECT id_utilisateur FROM utilisateur WHERE id_utilisateur=? AND statut IN ('actif','active') LOCK IN SHARE MODE");$q->execute([$userId]);if(!$q->fetch())throw new DomainException('Compte actif introuvable.',422);
            $q=$this->db->prepare('SELECT * FROM livreur WHERE id_livreur=? FOR UPDATE');$q->execute([$courierId]);$courier=$q->fetch()?:throw new DomainException('Livreur introuvable.',404);
            if((int)($courier['id_utilisateur']??0)===$userId)return;
            $q=$this->db->prepare('SELECT id_tournee FROM tournee WHERE id_livreur=? LIMIT 1');$q->execute([$courierId]);
            if($q->fetchColumn())throw new DomainException('Cette identité possède déjà un historique. Son compte ne peut pas être transféré à une autre personne.',409);
            $q=$this->db->prepare('SELECT id_tournee FROM tournee WHERE livreur_ouvert=?');$q->execute([$courierId]);if($q->fetchColumn())throw new DomainException('Terminez la tournée avant de changer de compte.',409);
            $q=$this->db->prepare('SELECT id_livreur FROM livreur WHERE id_utilisateur=? AND id_livreur<>?');$q->execute([$userId,$courierId]);if($q->fetchColumn())throw new DomainException('Ce compte est déjà associé à un livreur.',422);
            $this->execute('UPDATE livreur SET id_utilisateur=? WHERE id_livreur=?',[$userId,$courierId]);
            $this->dispatch($access->requireCurrentAdmin());
        });
    }

    private function manifestSql(): string
    {
        return "SELECT a.*,l.id_commande,l.statut AS livraison_statut,l.nom_destinataire_snapshot,l.prenom_destinataire_snapshot,l.telephone_snapshot,l.commune_snapshot,l.adresse_snapshot,l.repere_snapshot,l.instructions_snapshot,l.nom_zone_snapshot,
            c.numero_commande,c.statut AS commande_statut,c.stock_restitue_at,c.reservation_expire_at,p.statut AS paiement_statut,p.devise_reglement,p.montant_reglement,p.montant,c.total,c.devise_paiement,c.total_paiement,k.montant AS montant_collecte,k.devise AS devise_collecte
            FROM tentative_livraison a JOIN livraison l ON l.id_livraison=a.id_livraison JOIN commande c ON c.id_commande=l.id_commande
            JOIN paiement p ON p.id_commande=c.id_commande LEFT JOIN collecte_cash k ON k.id_tentative=a.id_tentative";
    }
    private function manifest(int $id): array {$q=$this->db->prepare($this->manifestSql().' WHERE a.id_tournee=? ORDER BY a.emplacement');$q->execute([$id]);return $q->fetchAll();}
    private function ownedTour(int $id,int $courier): array {$q=$this->db->prepare('SELECT * FROM tournee WHERE id_tournee=? AND id_livreur=? FOR UPDATE');$q->execute([$id,$courier]);return $q->fetch()?:throw new DomainException('Tournée introuvable.',404);}
    private function cash(int $id): array {$q=$this->db->prepare('SELECT p.*,m.nom AS mode,c.statut AS commande_statut,c.stock_restitue_at,c.reservation_expire_at,c.total,c.devise_paiement,c.total_paiement,c.devise_reference,c.taux_usd_cdf AS taux_commande FROM paiement p JOIN commande c ON c.id_commande=p.id_commande JOIN mode_paiement m ON m.id_mode_paiement=p.id_mode_paiement WHERE p.id_commande=? FOR UPDATE');$q->execute([$id]);$rows=$q->fetchAll();if(count($rows)!==1||$rows[0]['mode']!=='Espèces à la livraison')$this->conflict();return $rows[0];}
    private function cashAmount(array $p): array
    {
        $currency=$p['devise_paiement']??$p['devise_reglement']??'USD';
        $amount=$currency==='USD'?$p['total']:$p['total_paiement'];
        if(!in_array($currency,['USD','CDF'],true)||$p['devise_reference']!=='USD'||$amount===null||$p['taux_commande']===null||$p['taux_usd_cdf']===null||$this->minor($p['montant'])!==$this->minor($p['total'])
            ||($p['devise_reglement']!==null&&$p['devise_reglement']!==$currency)
            ||($p['montant_reglement']!==null&&$this->minor($p['montant_reglement'])!==$this->minor($amount))
            ||(string)$p['taux_usd_cdf']!==(string)$p['taux_commande'])$this->conflict();
        return [$currency,$amount];
    }
    private function minor(string $value): int {if(!preg_match('/^(\d{1,14})(?:\.(\d{1,2}))?$/D',$value,$m))throw new DomainException('Montant invalide.',422);return (int)$m[1]*100+(int)str_pad($m[2]??'',2,'0');}
    private function event(int $order,string $action,string $resource,string $type,?int $actor,array $data): void
    {
        $q=$this->db->prepare("INSERT INTO journal_commande(id_commande,id_administrateur,type_acteur,id_acteur,action,ressource,ancien_etat,nouvel_etat,donnees_complementaires) VALUES(?,?,?,?,?,?,'{}','{}',?)");
        $q->execute([$order,$type==='admin'?$actor:null,$type,$actor,$action,$resource,json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
    }
    private function execute(string $sql,array $args): void {$q=$this->db->prepare($sql);$q->execute($args);}
    private function requireInstalled(): void {if(!$this->installed())throw new DomainException('La mise à jour S1.15 doit être appliquée.',503);}
    private function conflict(): never {throw new DomainException('Cette action ne correspond plus à la situation actuelle. Rechargez la page.',409);}
    private function transaction(callable $operation): mixed
    {
        $this->db->beginTransaction();
        try{$this->lock();$result=$operation();$this->db->commit();return $result;}
        catch(Throwable $error){if($this->db->inTransaction())$this->db->rollBack();throw $error;}
    }
}
