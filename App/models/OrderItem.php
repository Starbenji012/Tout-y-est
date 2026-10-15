<?php

declare(strict_types=1);
namespace App\Models;
use PDO;

final class OrderItem
{
    public function __construct(private readonly PDO $database) {}

    public function create(array $line): void
    {
        $query = $this->database->prepare('INSERT INTO ligne_commande (id_commande,id_produit,id_variante,nom_produit_snapshot,sku_snapshot,description_variante_snapshot,quantite,prix_unitaire_applique,remise_unitaire_appliquee,total_ligne) VALUES (:order_id,:product_id,:variant_id,:name,:sku,:description,:quantity,:price,:discount,:total)');
        $query->execute($line);
    }

    public function forOrder(int $id): array
    {
        $query = $this->database->prepare('SELECT * FROM ligne_commande WHERE id_commande = ? ORDER BY id_variante');
        $query->execute([$id]);
        return $query->fetchAll();
    }
}
