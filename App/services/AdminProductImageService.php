<?php
declare(strict_types=1);
namespace App\Services;

use App\Models\ProductImage;
use App\Models\CatalogVisibility;
use DomainException;
use PDO;
use Throwable;

/** Le moteur reste indépendant de HTTP. Le contrôleur valide d'abord HttpImageUpload.
 * Le verrou produit sérialise comptage, principale et ordre, y compris pendant GD.
 * Les seuls fichiers publics sont des WebP générés ; chemin BDD = version large.
 */
final class AdminProductImageService
{
    private readonly string $storage;
    public function __construct(private readonly PDO $database,private readonly AdminAccessService $access,
        private readonly ProductImage $images,private readonly UploadService $processor,?string $storage=null)
    {
        // Injection serveur réservée aux tests isolés, jamais une valeur de formulaire.
        $this->storage=$storage??dirname(__DIR__,2).'/Public/uploads/products';
    }

    public function authorize(): void {$this->access->requireCurrentAdmin();}

    public function gallery(int $product): array
    {
        $this->authorize();
        $rows=$this->images->forProduct($product);
        foreach ($rows as &$row) {
            $path=(string)$row['chemin'];
            $thumb=preg_match('~^/?uploads/products/[a-f0-9]{32}-large\.webp$~D',$path)?str_replace('-large.webp','-thumb.webp',$path):$path;
            $row['thumbnail']=ProductService::localProductImage($thumb)??ProductService::localProductImage($path);
        }
        return $rows;
    }

    /** Même verrou que l'upload : aucune principale/position décidée dans le navigateur. */
    public function change(int $product,int $image,string $action): string
    {
        $this->authorize();
        if($product<1||$image<1)throw new DomainException('Photo introuvable pour ce produit.',404);
        if(!in_array($action,['primary','up','down','delete'],true))throw new DomainException('Action photo invalide.',422);
        if($this->database->inTransaction())throw new DomainException('Modification indisponible pour le moment.',503);
        $files=[];
        $this->database->beginTransaction();
        try {
            $this->access->requireCurrentAdmin(true);
            $query=$this->database->prepare('SELECT statut FROM produit WHERE id_produit=? FOR UPDATE');
            $query->execute([$product]);$status=$query->fetchColumn();
            if($status===false)throw new DomainException('Produit introuvable.',404);
            $rows=$this->images->forProduct($product);
            $ids=array_map('intval',array_column($rows,'id_image'));
            $position=array_search($image,$ids,true);
            if($position===false)throw new DomainException('Photo introuvable pour ce produit.',404);
            if($action==='delete') {
                if(count($rows)===1&&CatalogVisibility::isActive($status))throw new DomainException('Un produit actif doit conserver au moins une photo.',422);
                $files=$this->deletableFiles((string)$rows[$position]['chemin'],$image);
                $this->images->delete($product,$image);
                array_splice($rows,$position,1);array_splice($ids,$position,1);
            } elseif($action==='primary') {
                $this->images->primary($product,$image);
                foreach($rows as &$row)$row['image_principale']=(int)$row['id_image']===$image?1:0;
                unset($row);
            } else {
                $target=$position+($action==='up'?-1:1);
                if($target<0||$target>=count($ids))throw new DomainException('Cette photo est déjà à cette position limite.',422);
                [$ids[$position],$ids[$target]]=[$ids[$target],$ids[$position]];
            }
            // Répare aussi les anciennes galeries non vides sans principale.
            if($ids!==[]&&!array_filter($rows,static fn(array $row):bool=>(int)$row['image_principale']===1))$this->images->primary($product,$ids[0]);
            $this->images->order($product,$ids);
            $this->database->commit();
        } catch(Throwable $error) {
            if($this->database->inTransaction())$this->database->rollBack();
            throw $error;
        }
        // Jamais avant commit : une erreur SQL ne peut casser une ligne conservée.
        $clean=true;
        foreach($files as $file)if(is_file($file)&&!@unlink($file))$clean=false;
        if(!$clean) {
            error_log('Suppression photo : nettoyage des dérivés incomplet après commit.');
            return 'Photo retirée de la galerie. Le nettoyage de certains fichiers reste à effectuer par la maintenance.';
        }
        return match($action) {'primary'=>'Image principale mise à jour.','up','down'=>'Ordre des photos mis à jour.',default=>'Photo supprimée.'};
    }

    /** Seuls trois noms exacts générés par 3B-2 sont éligibles ; assets reste intact. */
    private function deletableFiles(string $path,int $image): array
    {
        if(!preg_match('~^/?uploads/products/([a-f0-9]{32})-large\.webp$~D',$path,$match)) {
            if(str_starts_with(ltrim($path,'/'),'uploads/'))throw new DomainException('Le chemin de cette photo nécessite une vérification avant suppression.',422);
            return [];
        }
        $root=realpath($this->storage);
        if($root===false||is_link($this->storage)||!is_dir($root))throw new DomainException('Le stockage des photos est indisponible.',503);
        $files=[];$paths=[];
        foreach(['thumb','medium','large'] as $size) {
            $name=$match[1].'-'.$size.'.webp';$file=$root.DIRECTORY_SEPARATOR.$name;
            $paths[]='uploads/products/'.$name;$paths[]='/uploads/products/'.$name;
            if(is_link($file)||(file_exists($file)&&(!is_file($file)||realpath($file)!==$file)))throw new DomainException('Le fichier photo nécessite une vérification avant suppression.',422);
            $files[]=$file;
        }
        // Une référence partagée importée manuellement ne doit jamais être cassée.
        return $this->images->pathsReferenced($paths,$image)?[]:$files;
    }

    /** Source locale déjà vérifiée comme upload HTTP par le contrôleur en production. */
    public function add(int $product,string $source): void
    {
        $this->authorize();
        if ($product<1) throw new DomainException('Produit introuvable.',404);
        if ($this->database->inTransaction()) throw new DomainException('Ajout indisponible pour le moment.',503);
        $processed=null;$created=[];
        $this->database->beginTransaction();
        try {
            $this->access->requireCurrentAdmin(true);
            $query=$this->database->prepare('SELECT id_produit FROM produit WHERE id_produit=? FOR UPDATE');
            $query->execute([$product]);
            if (!$query->fetchColumn()) throw new DomainException('Produit introuvable.',404);
            $rows=$this->images->forProduct($product);
            if (count($rows)>=6) throw new DomainException('La limite de 6 photos est atteinte pour ce produit.',422);
            $order=$rows===[]?0:max(array_column($rows,'ordre_affichage'))+1;
            if ($order>32767) throw new DomainException('Impossible d’ajouter une photo à cette galerie.',422);
            $processed=$this->processor->process($source);
            $root=realpath($this->storage);
            if ($root===false||is_link($this->storage)||!is_dir($root)||!is_writable($root)) {
                throw new DomainException('Le stockage des photos est indisponible.',503);
            }
            $opaque=bin2hex(random_bytes(16));
            foreach (['thumb','medium','large'] as $size) {
                $path=$root.DIRECTORY_SEPARATOR.$opaque.'-'.$size.'.webp';
                $destination=@fopen($path,'x+b');
                if ($destination===false) throw new DomainException('Enregistrement de la photo impossible.',503);
                $created[]=$path;
                $input=null;
                try {
                    $input=@fopen($processed['derivatives'][$size]['path'],'rb');
                    if ($input===false||stream_copy_to_stream($input,$destination)!==$processed['derivatives'][$size]['bytes']||!fflush($destination)) {
                        throw new DomainException('Enregistrement de la photo incomplet.',503);
                    }
                } finally {if (is_resource($input))fclose($input);fclose($destination);}
            }
            $this->images->insert($product,'uploads/products/'.$opaque.'-large.webp',$order,$rows===[]);
            $this->database->commit();
        } catch (Throwable $error) {
            if ($this->database->inTransaction())$this->database->rollBack();
            foreach ($created as $path) if (is_file($path)&&!@unlink($path))error_log('Nettoyage photo publique incomplet.');
            throw $error;
        } finally {
            if (is_array($processed)) {
                foreach (array_unique(array_column($processed['derivatives'],'path')) as $path) {
                    if (is_file($path)&&!@unlink($path))error_log('Nettoyage photo temporaire incomplet.');
                }
                if (is_dir($processed['temporary_directory'])&&!@rmdir($processed['temporary_directory']))error_log('Nettoyage dossier photo temporaire incomplet.');
            }
        }
    }
}
