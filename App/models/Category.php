<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class Category
{
    private ?array $columns = null;

    public function __construct(private readonly PDO $database)
    {
    }

    public function findActiveHierarchy(): array
    {
        $columns = $this->columns();
        $parentColumn = $this->firstAvailableColumn([
            'id_categorie_parent',
            'id_categorie_parente',
            'id_parent',
            'parent_id',
            'categorie_parent_id',
            'parent_categorie_id',
        ]);
        $statusCondition = in_array('statut', $columns, true)
            ? " WHERE LOWER(c.statut) NOT IN ('inactif', 'inactive', 'archive', 'supprime')"
            : '';
        $parentSelect = $parentColumn !== null
            ? 'c.`' . $parentColumn . '` AS parent_id'
            : 'NULL AS parent_id';
        $statement = $this->database->query(
            'SELECT c.id_categorie, c.nom, c.slug_, ' . $parentSelect . ',
                    (SELECT COUNT(*) FROM produit p
                     WHERE p.id_categorie = c.id_categorie
                       AND LOWER(p.statut) NOT IN (\'inactif\', \'inactive\', \'brouillon\', \'archive\', \'supprime\')) AS product_count
             FROM categorie c' . $statusCondition . '
             ORDER BY c.nom',
        );

        return $statement->fetchAll();
    }

    private function columns(): array
    {
        if ($this->columns === null) {
            $this->columns = $this->database
                ->query('SHOW COLUMNS FROM categorie')
                ->fetchAll(PDO::FETCH_COLUMN);
        }

        return $this->columns;
    }

    private function firstAvailableColumn(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $this->columns(), true)) {
                return $candidate;
            }
        }

        return null;
    }
}
