<?php

declare(strict_types=1);
namespace App\Models;
use PDO;

require_once __DIR__ . '/CatalogVisibility.php';

/** Lectures verrouillées et mouvements de stock sur la connexion de commande. */
final class ProductVariant
{
    public function __construct(private readonly PDO $database) {}

    public function lock(int $id): ?array
    {
        $query = $this->database->prepare('SELECT v.*, p.nom, p.statut AS produit_statut, c.statut AS categorie_statut FROM variante_produit v JOIN produit p ON p.id_produit = v.id_produit JOIN categorie c ON c.id_categorie = p.id_categorie WHERE v.id_variante = ? FOR UPDATE');
        $query->execute([$id]);
        $variant = $query->fetch() ?: null;
        if ($variant !== null) {
            $variant['options_pending_selection'] = false;
            if (CatalogVisibility::hasOptionsTable($this->database)) {
                // Lecture courante après verrou produit, même si une ancienne snapshot SQL existe.
                $options=$this->database->prepare('SELECT id_option FROM option_produit WHERE id_produit=? LIMIT 1 LOCK IN SHARE MODE');
                $options->execute([(int)$variant['id_produit']]);
                $variant['options_pending_selection']=$options->fetchColumn()!==false;
            }
        }
        return $variant;
    }

    public function reserve(int $id, int $quantity): bool
    {
        $query = $this->database->prepare('UPDATE variante_produit SET stock = stock - ? WHERE id_variante = ? AND stock >= ?');
        $query->execute([$quantity, $id, $quantity]);
        return $query->rowCount() === 1;
    }

    public function restore(int $id, int $quantity): void
    {
        $query = $this->database->prepare('UPDATE variante_produit SET stock = stock + ? WHERE id_variante = ?');
        $query->execute([$quantity, $id]);
        if ($query->rowCount() !== 1) throw new \RuntimeException('Variante de réservation introuvable.');
    }

    public function promotion(int $productId): string
    {
        $query = $this->database->prepare("SELECT pr.pourcentage FROM promotion pr JOIN beneficier b ON b.id_promotion = pr.id_promotion WHERE b.id_produit = ? AND pr.statut NOT IN ('inactif','inactive','brouillon','archive') AND NOW() BETWEEN pr.date_debut AND pr.date_fin ORDER BY pr.pourcentage DESC LIMIT 1 LOCK IN SHARE MODE");
        $query->execute([$productId]);
        return (string) ($query->fetchColumn() ?: '0');
    }
}
