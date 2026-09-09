<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/** Effectue uniquement les lectures de produits dans la base de données. */
final class Product
{
    private const ACTIVE_PROMOTION = "pr.statut NOT IN ('inactif', 'inactive', 'brouillon', 'archive') AND NOW() BETWEEN pr.date_debut AND pr.date_fin";

    /** Reçoit la connexion PDO centralisée. */
    public function __construct(private readonly PDO $database)
    {
    }

    /** Indique rapidement si la table contient au moins un produit. */
    public function hasProducts(): bool
    {
        return (int) $this->database->query('SELECT COUNT(*) FROM produit')->fetchColumn() > 0;
    }

    /** Retourne une page de catalogue filtrée et triée. */
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

    /** Charge un ensemble limité de candidats pour la recherche tolérante. */
    public function findSuggestionCandidates(string $query, int $limit = 80): array
    {
        $query = trim($query);
        $statement = $this->database->prepare(
            $this->selectSql(true)
            . " WHERE LOWER(p.statut) NOT IN ('inactif', 'inactive', 'brouillon', 'archive', 'supprime')"
            . ' AND (
                    p.nom LIKE :candidate_name
                    OR c.nom LIKE :candidate_category
                    OR p.description LIKE :candidate_description
                )
                ORDER BY
                    CASE
                        WHEN p.nom LIKE :candidate_prefix THEN 0
                        WHEN p.nom LIKE :candidate_order_name THEN 1
                        ELSE 2
                    END,
                    p.date_creation DESC
                LIMIT :limit',
        );
        $statement->bindValue(':candidate_name', '%' . $query . '%');
        $statement->bindValue(':candidate_category', '%' . $query . '%');
        $statement->bindValue(':candidate_description', '%' . $query . '%');
        $statement->bindValue(':candidate_prefix', $query . '%');
        $statement->bindValue(':candidate_order_name', '%' . $query . '%');
        $statement->bindValue(':limit', max(1, min(100, $limit)), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    /** Compte les produits correspondant aux mêmes filtres que le catalogue. */
    public function countCatalog(array $filters): int
    {
        [$where, $parameters] = $this->buildFilters($filters);
        $statement = $this->database->prepare(
            'SELECT COUNT(DISTINCT p.id_produit)
             FROM produit p
             INNER JOIN categorie c ON c.id_categorie = p.id_categorie
             INNER JOIN variante_produit v ON v.id_produit = p.id_produit' . $where,
        );

        $this->bindParameters($statement, $parameters);
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    /** Recherche un produit précis par son identifiant. */
    public function findById(int $productId): ?array
    {
        $statement = $this->database->prepare($this->selectSql() . ' WHERE p.id_produit = :product_id LIMIT 1');
        $statement->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $statement->execute();
        $product = $statement->fetch();

        return is_array($product) ? $product : null;
    }

    /** Recherche plusieurs produits à partir des identifiants reçus. */
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

    /** Recherche les produits correspondant aux variantes demandées. */
    public function findByVariantIds(array $variantIds): array
    {
        if ($variantIds === []) {
            return [];
        }

        $placeholders = [];
        $parameters = [];

        foreach (array_values($variantIds) as $index => $variantId) {
            $key = 'variant_id_' . $index;
            $placeholders[] = ':' . $key;
            $parameters[$key] = (int) $variantId;
        }

        $statement = $this->database->prepare(
            $this->selectSql(false, true)
            . ' WHERE v.id_variante IN (' . implode(', ', $placeholders) . ')'
            . " AND LOWER(v.statut) NOT IN ('inactif', 'inactive', 'archive', 'supprime')"
            . " AND LOWER(p.statut) NOT IN ('inactif', 'inactive', 'brouillon', 'archive', 'supprime')",
        );
        $this->bindParameters($statement, $parameters);
        $statement->execute();

        return $statement->fetchAll();
    }

    /** Retourne les catégories réellement utilisées par les produits. */
    public function categories(): array
    {
        $statement = $this->database->query(
            "SELECT c.slug, c.nom
             FROM categorie c
             WHERE LOWER(c.statut) NOT IN ('inactif', 'inactive', 'archive')
               ORDER BY c.nom",
        );

        return $statement->fetchAll();
    }

    /** Construit la sélection SQL commune aux différentes lectures de produits. */
    private function selectSql(bool $includeSearchAttributes = false, bool $exactVariant = false): string
    {
        $promotionCondition = self::ACTIVE_PROMOTION;
        $searchAttributes = $includeSearchAttributes
            ? "''"
            : "''";
        $stockSelection = $exactVariant
            ? 'v.stock'
            : "COALESCE((SELECT SUM(vs.stock) FROM variante_produit vs WHERE vs.id_produit = p.id_produit AND LOWER(vs.statut) NOT IN ('inactif', 'inactive')), 0)";
        $variantJoin = $exactVariant
            ? 'v.id_variante'
            : '(SELECT vr.id_variante
                    FROM variante_produit vr
                WHERE vr.id_produit = p.id_produit
                ORDER BY vr.id_variante
                LIMIT 1)';

        return "SELECT
                    p.id_produit,
                    p.nom,
                    p.slug,
                    p.description,
                    v.id_variante,
                    v.sku,
                    v.prix_reference,
                    p.date_creation,
                    c.nom AS categorie,
                    c.slug AS categorie_slug,
                    '' AS image,
                    {$searchAttributes} AS search_attributes,
                    0 AS note,
                    0 AS avis_count,
                    {$stockSelection} AS stock,
                    (SELECT MAX(pr.pourcentage)
                     FROM beneficier b
                     INNER JOIN promotion pr ON pr.id_promotion = b.id_promotion
                     WHERE b.id_produit = p.id_produit AND {$promotionCondition}) AS reduction
                FROM produit p
                INNER JOIN categorie c ON c.id_categorie = p.id_categorie
                INNER JOIN variante_produit v ON v.id_variante = {$variantJoin}";
    }

    /** Transforme les filtres validés en clauses SQL et paramètres préparés. */
    private function buildFilters(array $filters): array
    {
        $conditions = ["LOWER(p.statut) NOT IN ('inactif', 'inactive', 'brouillon', 'archive', 'supprime')"];
        $parameters = [];

        if (($filters['search'] ?? '') !== '') {
            $conditions[] = '(p.nom LIKE :search_name
                OR p.description LIKE :search_description
                OR c.nom LIKE :search_category)';
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

            $conditions[] = 'c.slug IN (' . implode(', ', $placeholders) . ')';
        }

        if ($filters['priceMin'] !== null) {
            $conditions[] = 'v.prix_reference >= :price_min';
            $parameters['price_min'] = $filters['priceMin'];
        }

        if ($filters['priceMax'] !== null) {
            $conditions[] = 'v.prix_reference <= :price_max';
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

        return [' WHERE ' . implode(' AND ', $conditions), $parameters];
    }

    /** Traduit les statuts métier en conditions SQL. */
    private function statusConditions(array $statuses): array
    {
        $conditions = [];
        $promotionCondition = self::ACTIVE_PROMOTION;

        if (in_array('promotion', $statuses, true)) {
            $conditions[] = "EXISTS (SELECT 1 FROM beneficier b INNER JOIN promotion pr ON pr.id_promotion = b.id_promotion WHERE b.id_produit = p.id_produit AND {$promotionCondition})";
        }

        if (in_array('new', $statuses, true)) {
            $conditions[] = 'p.date_creation >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
        }

        if (in_array('limited', $statuses, true)) {
            $conditions[] = '(SELECT COALESCE(SUM(vs.stock), 0) FROM variante_produit vs WHERE vs.id_produit = p.id_produit) BETWEEN 1 AND 5';
        }

        return $conditions;
    }

    /** Construit un ordre stable à partir d'un tri autorisé. */
    private function sortSql(string $sort): string
    {
        // Le prix affiché tient compte de la meilleure promotion active.
        $effectivePrice = 'v.prix_reference * (1 - (COALESCE(reduction, 0) / 100))';

        return match ($sort) {
            'price-asc' => $effectivePrice . ' ASC, p.nom ASC, p.id_produit DESC',
            'price-desc' => $effectivePrice . ' DESC, p.nom ASC, p.id_produit DESC',
            'popular' => 'avis_count DESC, note DESC, p.date_creation DESC, p.id_produit DESC',
            'rating' => 'note DESC, avis_count DESC, p.date_creation DESC, p.id_produit DESC',
            'promotion' => 'reduction DESC, p.date_creation DESC, p.id_produit DESC',
            default => 'p.date_creation DESC, p.id_produit DESC',
        };
    }

    /** Lie chaque valeur avec le type PDO adapté avant l'exécution. */
    private function bindParameters(\PDOStatement $statement, array $parameters): void
    {
        foreach ($parameters as $key => $value) {
            $statement->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
    }
}
