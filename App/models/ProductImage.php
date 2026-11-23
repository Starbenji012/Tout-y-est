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

    public function primary(int $product,int $image): void
    {
        $query=$this->database->prepare('UPDATE image_produit SET image_principale=0 WHERE id_produit=?');
        $query->execute([$product]);
        $query=$this->database->prepare('UPDATE image_produit SET image_principale=1 WHERE id_produit=? AND id_image=?');
        $query->execute([$product,$image]);
    }

    public function order(int $product,array $ids): void
    {
        $query=$this->database->prepare('UPDATE image_produit SET ordre_affichage=? WHERE id_produit=? AND id_image=?');
        foreach(array_values($ids) as $position=>$id)$query->execute([$position,$product,$id]);
    }

    public function delete(int $product,int $image): void
    {
        $query=$this->database->prepare('DELETE FROM image_produit WHERE id_produit=? AND id_image=?');
        $query->execute([$product,$image]);
    }

    public function pathsReferenced(array $paths,int $excluding): bool
    {
        $query=$this->database->prepare('SELECT COUNT(*) FROM image_produit WHERE id_image<>? AND chemin IN ('.implode(',',array_fill(0,count($paths),'?')).')');
        $query->execute(array_merge([$excluding],$paths));
        return (int)$query->fetchColumn()>0;
    }
}
