<?php
declare(strict_types=1);
namespace App\Services;

use App\Models\ProductImage;
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
