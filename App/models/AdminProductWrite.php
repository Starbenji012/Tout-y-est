<?php
declare(strict_types=1);
namespace App\Models;

use PDO;

/** Accès Admin dédié ; aucune suppression ni écriture sur une variante existante. */
final class AdminProductWrite
{
    public function __construct(private readonly PDO $database) {}

    public function find(int $id,bool $lock=false): ?array
    {
        $query=$this->database->prepare('SELECT id_produit,nom,description,id_categorie,slug,statut FROM produit WHERE id_produit=?'.($lock?' FOR UPDATE':''));
        $query->execute([$id]);return $query->fetch() ?: null;
    }

    public function categories(): array
    {
        return $this->database->query('SELECT id_categorie,nom,statut FROM categorie ORDER BY nom,id_categorie')->fetchAll();
    }

    public function categoryExists(int $id): bool
    {
        $query=$this->database->prepare('SELECT id_categorie FROM categorie WHERE id_categorie=? LOCK IN SHARE MODE');
        $query->execute([$id]);return $query->fetchColumn()!==false;
    }

    public function variants(int $id): array
    {
        $query=$this->database->prepare('SELECT id_variante,sku,description_variante,prix_reference,devise_reference,stock,statut FROM variante_produit WHERE id_produit=? ORDER BY id_variante');
        $query->execute([$id]);return $query->fetchAll();
    }

    public function imagePaths(int $id): array
    {
        $query=$this->database->prepare('SELECT chemin FROM image_produit WHERE id_produit=?');
        $query->execute([$id]);return $query->fetchAll(PDO::FETCH_COLUMN);
    }

    public function skuExists(string $sku): bool
    {
        $query=$this->database->prepare('SELECT 1 FROM variante_produit WHERE sku=? LIMIT 1');
        $query->execute([$sku]);return $query->fetchColumn()!==false;
    }

    /** Insert only the draft product; the service writes its variant in the same transaction. */
    public function createDraft(array $data,string $slug): int
    {
        $query=$this->database->prepare("INSERT INTO produit(nom,description,id_categorie,slug,statut) VALUES(?,?,?,?,'brouillon')");
        $query->execute([$data['nom'],$data['description'],$data['id_categorie'],$slug]);
        $id=(int)$this->database->lastInsertId();
        return $id;
    }

    public function update(int $id,array $data,string $status): void
    {
        // Slug et variantes restent intacts, même après renommage du produit.
        $query=$this->database->prepare('UPDATE produit SET nom=?,description=?,id_categorie=?,statut=? WHERE id_produit=?');
        $query->execute([$data['nom'],$data['description'],$data['id_categorie'],$status,$id]);
    }
}
