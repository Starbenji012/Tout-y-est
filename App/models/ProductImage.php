<?php
declare(strict_types=1);
namespace App\Models;

use PDO;

final class ProductImage
{
    public function __construct(private readonly PDO $database) {}

    public function forProduct(int $id): array
    {
        $query=$this->database->prepare('SELECT id_image,chemin,ordre_affichage,image_principale FROM image_produit WHERE id_produit=? ORDER BY ordre_affichage,id_image');
        $query->execute([$id]);
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insert(int $product,string $path,int $order,bool $primary): void
    {
        $query=$this->database->prepare('INSERT INTO image_produit(id_produit,chemin,ordre_affichage,image_principale) VALUES(?,?,?,?)');
        $query->execute([$product,$path,$order,(int)$primary]);
    }
}
