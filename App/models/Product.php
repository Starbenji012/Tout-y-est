<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class Product
{
    private const ACTIVE_PROMOTION = "pr.statut NOT IN ('inactif', 'inactive', 'brouillon', 'archive') AND NOW() BETWEEN pr.date_debut AND pr.date_fin";

    public function __construct(private readonly PDO $database)
    {
    }

    public function hasProducts(): bool
    {
        return (int) $this->database->query('SELECT COUNT(*) FROM produit')->fetchColumn() > 0;
    }

    public function findCatalog(array $filters, string $sort, int $limit, int $offset): array
    {
        [$where, $parameters] = $this->buildFilters($filters);
        $sql = $this->selectSql() . $where . ' ORDER BY ' . $this->sortSql($sort) . ' LIMIT :limit OFFSET :offset';
        $statement = $this->database->prepare($sql);

        $this->bindParameters($statement, $parameters);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function countCatalog(array $filters): int
    {
        [$where, $parameters] = $this->buildFilters($filters);
        $statement = $this->database->prepare(
            'SELECT COUNT(DISTINCT p.id_produit)
             FROM produit p
             INNER JOIN categorie c ON c.id_categorie = p.id_categorie
             LEFT JOIN image_produit i ON i.id_image = p.id_image' . $where,
        );

        $this->bindParameters($statement, $parameters);
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    public function findById(int $productId): ?array
    {
        $statement = $this->database->prepare($this->selectSql() . ' WHERE p.id_produit = :product_id LIMIT 1');
        $statement->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $statement->execute();
        $product = $statement->fetch();

        return is_array($product) ? $product : null;
    }

    public function findByIds(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $placeholders = [];
        $parameters = [];

        foreach (array_values($productIds) as $index => $productId) {
            $key = 'product_id_' . $index;
            $placeholders[] = ':' . $key;
            $parameters[$key] = (int) $productId;
        }

        $statement = $this->database->prepare(
            $this->selectSql()
            . ' WHERE p.id_produit IN (' . implode(', ', $placeholders) . ')'
            . " AND LOWER(p.statut) NOT IN ('inactif', 'inactive', 'brouillon', 'archive', 'supprime')",
        );
        $this->bindParameters($statement, $parameters);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function categories(): array
    {
        $statement = $this->database->query(
            "SELECT c.slug_, c.nom
             FROM categorie c
             WHERE LOWER(c.statut) NOT IN ('inactif', 'inactive', 'archive')
             ORDER BY c.nom",
        );

        return $statement->fetchAll();
    }

    private function selectSql(): string
    {
        $promotionCondition = self::ACTIVE_PROMOTION;

        return "SELECT
                    p.id_produit,
                    p.nom,
                    p.slug,
                    p.description,
                    p.prix_base,
                    p.date_creation,
                    c.nom AS categorie,
                    c.slug_ AS categorie_slug,
                    i.chemin AS image,
                    COALESCE((SELECT AVG(a.note) FROM avis a WHERE a.id_produit = p.id_produit AND LOWER(a.status) NOT IN ('rejete', 'rejected')), 0) AS note,
                    (SELECT COUNT(*) FROM avis a WHERE a.id_produit = p.id_produit AND LOWER(a.status) NOT IN ('rejete', 'rejected')) AS avis_count,
                    COALESCE((SELECT SUM(v.stock) FROM variante_produit v WHERE v.id_produit = p.id_produit AND LOWER(v.status) NOT IN ('inactif', 'inactive')), 0) AS stock,
                    (SELECT MAX(pr.pourcentage)
                     FROM benefici b
                     INNER JOIN promotion pr ON pr.id_promotion = b.id_promotion
                     WHERE b.id_produit = p.id_produit AND {$promotionCondition}) AS reduction
                FROM produit p
                INNER JOIN categorie c ON c.id_categorie = p.id_categorie
                LEFT JOIN image_produit i ON i.id_image = p.id_image";
    }

    private function buildFilters(array $filters): array
    {
        $conditions = ["LOWER(p.statut) NOT IN ('inactif', 'inactive', 'brouillon', 'archive', 'supprime')"];
        $parameters = [];

        if (($filters['search'] ?? '') !== '') {
            $conditions[] = '(p.nom LIKE :search_name OR p.description LIKE :search_description OR c.nom LIKE :search_category)';
            $search = '%' . $filters['search'] . '%';
            $parameters['search_name'] = $search;
            $parameters['search_description'] = $search;
            $parameters['search_category'] = $search;
        }

        if (($filters['categories'] ?? []) !== []) {
            $placeholders = [];

            foreach ($filters['categories'] as $index => $category) {
                $key = 'category_' . $index;
                $placeholders[] = ':' . $key;
                $parameters[$key] = $category;
            }

            $conditions[] = 'c.slug_ IN (' . implode(', ', $placeholders) . ')';
        }

        if ($filters['priceMin'] !== null) {
            $conditions[] = 'p.prix_base >= :price_min';
            $parameters['price_min'] = $filters['priceMin'];
        }

        if ($filters['priceMax'] !== null) {
            $conditions[] = 'p.prix_base <= :price_max';
            $parameters['price_max'] = $filters['priceMax'];
        }

        $statusConditions = $this->statusConditions($filters['statuses'] ?? []);

        if ($statusConditions !== []) {
            $conditions[] = '(' . implode(' OR ', $statusConditions) . ')';
        }

        if (($filters['availability'] ?? '') === 'in-stock') {
            $conditions[] = 'EXISTS (SELECT 1 FROM variante_produit v WHERE v.id_produit = p.id_produit AND v.stock > 0)';
        } elseif (($filters['availability'] ?? '') === 'out-of-stock') {
            $conditions[] = 'NOT EXISTS (SELECT 1 FROM variante_produit v WHERE v.id_produit = p.id_produit AND v.stock > 0)';
        }

        if (($filters['rating'] ?? 0) > 0) {
            $conditions[] = 'COALESCE((SELECT AVG(a.note) FROM avis a WHERE a.id_produit = p.id_produit), 0) >= :rating';
            $parameters['rating'] = $filters['rating'];
        }

        return [' WHERE ' . implode(' AND ', $conditions), $parameters];
    }

    private function statusConditions(array $statuses): array
    {
        $conditions = [];
        $promotionCondition = self::ACTIVE_PROMOTION;

        if (in_array('promotion', $statuses, true)) {
            $conditions[] = "EXISTS (SELECT 1 FROM benefici b INNER JOIN promotion pr ON pr.id_promotion = b.id_promotion WHERE b.id_produit = p.id_produit AND {$promotionCondition})";
        }

        if (in_array('new', $statuses, true)) {
            $conditions[] = 'p.date_creation >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
        }

        if (in_array('limited', $statuses, true)) {
            $conditions[] = '(SELECT COALESCE(SUM(v.stock), 0) FROM variante_produit v WHERE v.id_produit = p.id_produit) BETWEEN 1 AND 5';
        }

        return $conditions;
    }

    private function sortSql(string $sort): string
    {
        return match ($sort) {
            'price-asc' => 'p.prix_base ASC, p.nom ASC',
            'price-desc' => 'p.prix_base DESC, p.nom ASC',
            'popular' => 'avis_count DESC, note DESC, p.date_creation DESC',
            'promotion' => 'reduction DESC, p.date_creation DESC',
            default => 'p.date_creation DESC, p.id_produit DESC',
        };
    }

    private function bindParameters(\PDOStatement $statement, array $parameters): void
    {
        foreach ($parameters as $key => $value) {
            $statement->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
    }
}
