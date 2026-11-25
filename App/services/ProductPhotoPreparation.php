<?php
declare(strict_types=1);
namespace App\Services;

use DomainException;
use PDO;
use Throwable;

/** Tickets privés, quota réservé avant GD. Aucun original n'est conservé.
 * Les fichiers promus restent traçables après rollback/crash jusqu'à réconciliation.
 */
final class ProductPhotoPreparation
{
    private readonly string $privateRoot;
    private readonly string $publicRoot;
    public function __construct(private readonly PDO $db, private readonly AdminAccessService $access,
        private readonly UploadService $processor, ?string $privateRoot=null, ?string $publicRoot=null)
    {
        $namespace=hash('sha256',(string)$db->query('SELECT CONCAT(@@hostname, DATABASE())')->fetchColumn());
        $this->privateRoot=$privateRoot??sys_get_temp_dir().'/tye-prepared-'.substr($namespace,0,24);
        $this->publicRoot=$publicRoot??dirname(__DIR__,2).'/Public/uploads/products';
    }

    public static function token(mixed $value): string
    {
        if (!is_string($value)||!preg_match('/^[a-f0-9]{32}$/D',$value)) throw new DomainException('Ticket photo invalide.',422);
        return $value;
    }

    public static function guardPostSize(): void
    {
        $limit=trim((string)ini_get('post_max_size'));
        $bytes=(float)$limit*match(strtolower(substr($limit,-1))){'g'=>1073741824,'m'=>1048576,'k'=>1024,default=>1};
        if ($bytes>0&&(float)($_SERVER['CONTENT_LENGTH']??0)>$bytes) throw new DomainException('Envoi trop volumineux. Envoyez une seule photo de 10 Mio maximum à la fois.',413);
    }

    private function batch(string $batch,bool $create=false,bool $lock=false): array
    {
        self::token($batch);$owner=$this->access->requireCurrentAdmin();
        if ($create) {
            $q=$this->db->prepare('INSERT IGNORE INTO product_photo_batch(ticket,owner_id,expires_at) VALUES(?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 24 HOUR))');$q->execute([$batch,$owner]);
        }
        $q=$this->db->prepare('SELECT *,expires_at>UTC_TIMESTAMP() AS valid FROM product_photo_batch WHERE ticket=?'.($lock?' FOR UPDATE':''));$q->execute([$batch]);$row=$q->fetch();
        if (!$row||(int)$row['owner_id']!==$owner) throw new DomainException('Préparation photo introuvable.',404);
        if (!(int)$row['valid']||$row['product_id']!==null) throw new DomainException('Cette préparation a expiré ou a déjà été utilisée. Rechargez le formulaire.',409);
        return $row;
    }

    public function listing(string $batch): array
    {
        self::token($batch);$owner=$this->access->requireCurrentAdmin();
        $q=$this->db->prepare('SELECT owner_id FROM product_photo_batch WHERE ticket=?');$q->execute([$batch]);$existing=$q->fetchColumn();
        if($existing===false)return [];
        $this->batch($batch);
        $q=$this->db->prepare("SELECT ticket FROM product_photo_item WHERE batch_ticket=? AND state='ready' ORDER BY updated_at,ticket");$q->execute([$batch]);
        return $q->fetchAll(PDO::FETCH_COLUMN);
    }

    public function prepare(string $batch,string $ticket,string $source): string
    {
        self::token($batch);self::token($ticket);$this->access->requireCurrentAdmin();
        if (!is_file($source)||filesize($source)>10*1048576) throw new DomainException('Une photo doit peser au maximum 10 Mio.',422);
        $hash=hash_file('sha256',$source);
        $lock=$this->imageLock($batch,$hash);
        $q=$this->db->prepare('SELECT GET_LOCK(?,0)');$q->execute([$lock]);
        if((int)$q->fetchColumn()!==1)throw new DomainException('Cette photo est encore en traitement. Réessayez dans un instant.',409);
        try{return $this->prepareLocked($batch,$ticket,$source,$hash);}
        finally{$q=$this->db->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lock]);}
    }

    private function prepareLocked(string $batch,string $ticket,string $source,string $hash): string
    {
        $owner=$this->access->requireCurrentAdmin();
        // Un verrou commun bref couvre aussi les quotas entre lots et administrateurs.
        CatalogueWriteLock::acquire($this->db);
        try {
            $this->db->beginTransaction();
            try {
                $this->batch($batch,true,true);
                $q=$this->db->prepare('SELECT * FROM product_photo_item WHERE batch_ticket=? AND (ticket=? OR source_hash=?) FOR UPDATE');$q->execute([$batch,$ticket,$hash]);$rows=$q->fetchAll();
                foreach($rows as $row) {
                    if ($row['ticket']===$ticket&&$row['source_hash']!==$hash) throw new DomainException('Ce ticket appartient à un autre fichier.',409);
                    if ($row['state']==='ready'&&$row['source_hash']===$hash) {$this->db->commit();return $row['ticket'];}
                    // Le verrou de cette source est détenu ici : une ligne processing
                    // est donc la trace d'un ancien processus arrêté, récupérable sans course.
                    if ($row['ticket']!==$ticket) $ticket=$row['ticket'];
                }
                $q=$this->db->prepare("SELECT COUNT(*) FROM product_photo_item WHERE batch_ticket=? AND state IN ('ready','processing')");$q->execute([$batch]);
                if((int)$q->fetchColumn()>=6&&($rows===[]||$rows[0]['state']!=='processing'))throw new DomainException('Six photos maximum par produit.',422);
                $q=$this->db->prepare("SELECT COUNT(*) FROM product_photo_item i JOIN product_photo_batch b ON b.ticket=i.batch_ticket WHERE b.product_id IS NULL AND b.expires_at>UTC_TIMESTAMP() AND i.state IN ('ready','processing') AND b.owner_id=?");$q->execute([$owner]);
                if((int)$q->fetchColumn()>=24&&($rows===[]||$rows[0]['state']!=='processing'))throw new DomainException('Quota atteint : terminez vos préparations ou retirez des photos.',429);
                $global=(int)$this->db->query("SELECT COUNT(*) FROM product_photo_item i JOIN product_photo_batch b ON b.ticket=i.batch_ticket WHERE b.product_id IS NULL AND b.expires_at>UTC_TIMESTAMP() AND i.state IN ('ready','processing')")->fetchColumn();
                if($global>=192&&($rows===[]||$rows[0]['state']!=='processing'))throw new DomainException('Les préparations sont temporairement saturées.',503);
                // Les fichiers retirés restent comptés jusqu'au nettoyage : pas de contournement du quota disque.
                $q=$this->db->prepare('SELECT COUNT(*) FROM product_photo_item i JOIN product_photo_batch b ON b.ticket=i.batch_ticket WHERE b.owner_id=?');$q->execute([$owner]);
                if($rows===[]&&(int)$q->fetchColumn()>=48)throw new DomainException('Quota de préparation atteint. Attendez le nettoyage des préparations expirées.',429);
                if($rows===[]&&(int)$this->db->query('SELECT COUNT(*) FROM product_photo_item')->fetchColumn()>=384)throw new DomainException('Stockage préparatoire saturé. Réessayez après la maintenance.',503);
                $q=$this->db->prepare("INSERT INTO product_photo_item(batch_ticket,ticket,source_hash,state) VALUES(?,?,?,'processing') ON DUPLICATE KEY UPDATE state='processing',updated_at=UTC_TIMESTAMP()");$q->execute([$batch,$ticket,$hash]);
                $this->db->commit();
            } catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
        } finally {CatalogueWriteLock::release($this->db);}
        $processed=null;
        try {
            $directory=$this->directory($batch,$ticket);
            if(!is_dir($directory)&&!mkdir($directory,0700,true))throw new DomainException('Stockage privé indisponible.',503);
            $processed=$this->processor->process($source,$directory);
            $manifest=[];
            foreach(['thumb','medium','large'] as $size) {
                $input=$processed['derivatives'][$size]['path'];
                $target=$directory.'/'.$size.'.webp';
                if(!copy($input,$target))throw new DomainException('Préparation interrompue. Réessayez.',503);
                $manifest[$size]=['sha256'=>hash_file('sha256',$target),'bytes'=>filesize($target)];
            }
            // Le nettoyage ne peut pas effacer un traitement en cours : expiration 24 h.
            $this->batch($batch);
            $q=$this->db->prepare("UPDATE product_photo_item SET manifest=?,state='ready',updated_at=UTC_TIMESTAMP() WHERE batch_ticket=? AND ticket=? AND state='processing'");$q->execute([json_encode($manifest,JSON_THROW_ON_ERROR),$batch,$ticket]);
            if($q->rowCount()!==1)throw new DomainException('Préparation interrompue.',409);
            return $ticket;
        } catch(Throwable $e) {
            $q=$this->db->prepare("UPDATE product_photo_item SET state='removed',updated_at=UTC_TIMESTAMP() WHERE batch_ticket=? AND ticket=? AND state='processing'");$q->execute([$batch,$ticket]);
            throw $e;
        } finally {
            if($processed!==null){foreach(array_unique(array_column($processed['derivatives'],'path')) as $file)if(is_file($file))@unlink($file);@rmdir($processed['temporary_directory']);}
        }
    }

    public function remove(string $batch,string $ticket): void
    {
        self::token($ticket);CatalogueWriteLock::acquire($this->db);
        try {
            $this->batch($batch);
            $q=$this->db->prepare("UPDATE product_photo_item SET state='removed',updated_at=UTC_TIMESTAMP() WHERE batch_ticket=? AND ticket=? AND state='ready'");$q->execute([$batch,$ticket]);
            // Garder le manifeste jusqu'au nettoyage, y compris après une tentative de publication.
        } finally {CatalogueWriteLock::release($this->db);}
    }

    public function preview(string $batch,string $ticket): string
    {
        self::token($ticket);$this->batch($batch);
        $q=$this->db->prepare("SELECT manifest FROM product_photo_item WHERE batch_ticket=? AND ticket=? AND state='ready'");$q->execute([$batch,$ticket]);$manifest=$q->fetchColumn();
        if(!$manifest)throw new DomainException('Photo indisponible.',404);
        $file=$this->directory($batch,$ticket).'/thumb.webp';
        if(!is_file($file))throw new DomainException('Photo expirée. Retirez-la puis renvoyez-la.',409);
        return $file;
    }

    /** Appelé dans la transaction produit APRÈS la recherche de replay SKU.
     * Les copies sont déterministes et vérifiées ; on ne les efface pas sur commit ambigu.
     */
    public function attach(string $batch,array $ids,string $primary,int $product): void
    {
        if(!$this->db->inTransaction())throw new \LogicException('Transaction produit requise.');
        if(count($ids)>6||count(array_unique($ids))!==count($ids))throw new DomainException('Six photos distinctes maximum.',422);
        if($ids===[])return;
        $this->batch($batch,false,true);
        if(!in_array($primary,$ids,true))throw new DomainException('Choisissez une photo principale.',422);
        if(!is_dir($this->publicRoot)&&!mkdir($this->publicRoot,0755,true))throw new DomainException('Stockage photo indisponible.',503);
        $images=new \App\Models\ProductImage($this->db);
        foreach($ids as $position=>$id){
            self::token($id);
            $q=$this->db->prepare("SELECT manifest FROM product_photo_item WHERE batch_ticket=? AND ticket=? AND state='ready' FOR UPDATE");$q->execute([$batch,$id]);$raw=$q->fetchColumn();
            if(!$raw)throw new DomainException('Une photo n’est pas prête ou a expiré. Vérifiez vos photos.',422);
            $manifest=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
            $stem=$this->stem($batch,$id);
            foreach(['thumb','medium','large'] as $size){
                $source=$this->directory($batch,$id).'/'.$size.'.webp';$target=$this->publicRoot.'/'.$stem.'-'.$size.'.webp';
                $hash=$manifest[$size]['sha256'];
                if(!is_file($source)||hash_file('sha256',$source)!==$hash)throw new DomainException('Une photo préparée est manquante ou altérée. Retirez-la puis renvoyez-la.',422);
                if(is_file($target)&&hash_file('sha256',$target)!==$hash){
                    $ref=$this->db->prepare('SELECT COUNT(*) FROM image_produit WHERE chemin IN (?,?)');$path='uploads/products/'.$stem.'-large.webp';$ref->execute([$path,'/'.$path]);
                    if((int)$ref->fetchColumn()>0||!unlink($target))throw new DomainException('Une copie photo référencée est incohérente. Intervention nécessaire.',503);
                }
                if(!is_file($target)){
                    $out=@fopen($target,'x+b');if(!$out)throw new DomainException('Copie photo impossible. Réessayez.',503);
                    $in=fopen($source,'rb');try{
                        if(stream_copy_to_stream($in,$out)!==$manifest[$size]['bytes']||!fflush($out)||(function_exists('fsync')&&!fsync($out)))throw new DomainException('Copie photo interrompue. Réessayez.',503);
                    }finally{fclose($in);fclose($out);}
                }
                if(hash_file('sha256',$target)!==$hash)throw new DomainException('Copie photo incomplète. Une intervention de maintenance est nécessaire.',503);
            }
            $images->insert($product,'uploads/products/'.$stem.'-large.webp',$position,$id===$primary);
            $q=$this->db->prepare("UPDATE product_photo_item SET state='consumed' WHERE batch_ticket=? AND ticket=?");$q->execute([$batch,$id]);
        }
        $q=$this->db->prepare('UPDATE product_photo_batch SET product_id=? WHERE ticket=?');$q->execute([$product,$batch]);
    }

    private function directory(string $batch,string $ticket): string {self::token($batch);self::token($ticket);return $this->privateRoot.'/'.$batch.'/'.$ticket;}
    private function stem(string $batch,string $ticket): string {return substr(hash('sha256',$batch.':'.$ticket),0,32);}
    private function imageLock(string $batch,string $hash): string {return substr('tye:photo:'.hash('sha256',$this->privateRoot.':'.$batch.':'.$hash),0,64);}

    /** Maintenance explicite et ciblée : jamais de glob sur les photos publiques.
     * La même exclusion catalogue protège les commits et les preuves de référence SQL.
     */
    public function cleanup(int $limit=100): int
    {
        CatalogueWriteLock::acquire($this->db);$count=0;
        try {
            $rows=$this->db->query('SELECT ticket FROM product_photo_batch WHERE expires_at<UTC_TIMESTAMP() AND cleaned_at IS NULL ORDER BY expires_at LIMIT '.max(1,min(100,$limit)))->fetchAll(PDO::FETCH_COLUMN);
            foreach($rows as $batch){
                $q=$this->db->prepare('SELECT ticket,source_hash FROM product_photo_item WHERE batch_ticket=?');$q->execute([$batch]);$clean=true;
                foreach($q->fetchAll() as $item){
                    $id=$item['ticket'];
                    $busy=$this->db->prepare('SELECT IS_FREE_LOCK(?)');$busy->execute([$this->imageLock($batch,$item['source_hash'])]);
                    if((int)$busy->fetchColumn()!==1){$clean=false;continue;}
                    $stem=$this->stem($batch,$id);$path='uploads/products/'.$stem.'-large.webp';
                    $ref=$this->db->prepare('SELECT COUNT(*) FROM image_produit WHERE chemin IN (?,?)');$ref->execute([$path,'/'.$path]);$referenced=(int)$ref->fetchColumn()>0;
                    foreach(['thumb','medium','large'] as $size){
                        foreach(array_filter([$this->directory($batch,$id).'/'.$size.'.webp',!$referenced?$this->publicRoot.'/'.$stem.'-'.$size.'.webp':null]) as $file){
                            if(is_file($file)&&!@unlink($file))$clean=false;
                        }
                    }
                    // Les sous-dossiers GD sont réservés à ce ticket, même si PHP s'est arrêté avant le manifeste.
                    $directory=$this->directory($batch,$id);
                    if(is_dir($directory))foreach(new \DirectoryIterator($directory) as $child){
                        if(!$child->isDir()||$child->isLink()||!preg_match('/^tye-image-[a-f0-9]{32}$/D',$child->getFilename()))continue;
                        foreach(new \DirectoryIterator($child->getPathname()) as $file){
                            if($file->isFile()&&!$file->isLink()&&preg_match('/^[a-f0-9]{32}\.webp$/D',$file->getFilename())&&!@unlink($file->getPathname()))$clean=false;
                        }
                        if(!@rmdir($child->getPathname()))$clean=false;
                    }
                    if(is_dir($this->directory($batch,$id))&&!@rmdir($this->directory($batch,$id)))$clean=false;
                }
                if($clean){
                    @rmdir($this->privateRoot.'/'.$batch);
                    $q=$this->db->prepare('DELETE FROM product_photo_item WHERE batch_ticket=?');$q->execute([$batch]);
                    // Tombstone compact : un ancien ticket ne redevient jamais utilisable.
                    $q=$this->db->prepare('UPDATE product_photo_batch SET cleaned_at=UTC_TIMESTAMP() WHERE ticket=?');$q->execute([$batch]);$count++;
                }
            }
            return $count;
        } finally {CatalogueWriteLock::release($this->db);}
    }
}
