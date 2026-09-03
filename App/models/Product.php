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
        $fragment = function_exists('mb_substr') ? mb_substr($query, 0, 3) : substr($query, 0, 3);
        $statement = $this->database->prepare(
            $this->selectSql(true)
            . " WHERE LOWER(p.statut) NOT IN ('inactif', 'inactive', 'brouillon', 'archive', 'supprime')"
            . ' AND (
                    p.nom LIKE :candidate_name
                    OR c.nom LIKE :candidate_category
                    OR p.nom LIKE :candidate_fragment
                    OR SOUNDEX(p.nom) = SOUNDEX(:candidate_soundex)
                    OR EXISTS (
                        SELECT 1
                        FROM `valeur_caractéristique` sv
                        INNER JOIN `caractéristique` sc ON sc.id_caracteristique = sv.id_caracteristique
                        WHERE sv.id_produit = p.id_produit
                          AND LOWER(sc.nom) IN (\'marque\', \'marques\', \'brand\', \'fabricant\')
                          AND (sv.valeur LIKE :candidate_brand OR sv.valeur LIKE :candidate_brand_fragment)
                    )
                )
                ORDER BY
                    CASE
                        WHEN p.nom LIKE :candidate_prefix THEN 0
                        WHEN p.nom LIKE :candidate_order_name THEN 1
                        ELSE 2
                    END,
                    avis_count DESC,
                    note DESC,
                    p.date_creation DESC
                LIMIT :limit',
        );
        $statement->bindValue(':candidate_name', '%' . $query . '%');
        $statement->bindValue(':candidate_category', '%' . $query . '%');
        $statement->bindValue(':candidate_fragment', '%' . $fragment . '%');
        $statement->bindValue(':candidate_soundex', $query);
        $statement->bindValue(':candidate_brand', '%' . $query . '%');
        $statement->bindValue(':candidate_brand_fragment', '%' . $fragment . '%');
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
             LEFT JOIN image_produit i ON i.id_image = p.id_image' . $where,
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

    /** Retourne les catégories réellement utilisées par les produits. */
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

    /** Construit la sélection SQL commune aux différentes lectures de produits. */
    private function selectSql(bool $includeSearchAttributes = false): string
    {
        $promotionCondition = self::ACTIVE_PROMOTION;
        $searchAttributes = $includeSearchAttributes
            ? "COALESCE((SELECT GROUP_CONCAT(DISTINCT vc.valeur SEPARATOR ' ')
                         FROM `valeur_caractéristique` vc
                         INNER JOIN `caractéristique` cc ON cc.id_caracteristique = vc.id_caracteristique
                         WHERE vc.id_produit = p.id_produit
                           AND LOWER(cc.nom) IN ('marque', 'marques', 'brand', 'fabricant')), '')"
            : "''";

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
                    {$searchAttributes} AS search_attributes,
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

    /** Transforme les filtres validés en clauses SQL et paramètres préparés. */
    private function buildFilters(array $filters): array
    {
        $conditions = ["LOWER(p.statut) NOT IN ('inactif', 'inactive', 'brouillon', 'archive', 'supprime')"];
        $parameters = [];

        if (($filters['search'] ?? '') !== '') {
            $conditions[] = '(p.nom LIKE :search_name
                OR p.description LIKE :search_description
                OR c.nom LIKE :search_category
                OR EXISTS (
                    SELECT 1
                    FROM `valeur_caractéristique` sv
                    INNER JOIN `caractéristique` sc ON sc.id_caracteristique = sv.id_caracteristique
                    WHERE sv.id_produit = p.id_produit
                      AND LOWER(sc.nom) IN (\'marque\', \'marques\', \'brand\', \'fabricant\')
                      AND sv.valeur LIKE :search_brand
                ))';
            $search = '%' . $filters['search'] . '%';
            $parameters['search_name'] = $search;
            $parameters['search_description'] = $search;
            $parameters['search_category'] = $search;
            $parameters['search_brand'] = $search;
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

        foreach ($filters['attributes'] ?? [] as $attribute => $values) {
            if ($values === []) {
                continue;
            }

            $valuePlaceholders = [];

            foreach ($values as $index => $value) {
                $key = 'attribute_' . $attribute . '_' . $index;
                $valuePlaceholders[] = ':' . $key;
                $parameters[$key] = $value;
            }

            $nameKey = 'attribute_name_' . $attribute;
            $parameters[$nameKey] = '%' . $attribute . '%';
            $conditions[] = 'EXISTS (
                SELECT 1
                FROM `valeur_caractéristique` vf
                INNER JOIN `caractéristique` cf ON cf.id_caracteristique = vf.id_caracteristique
                WHERE vf.id_produit = p.id_produit
                  AND LOWER(cf.nom) LIKE :' . $nameKey . '
                  AND vf.valeur IN (' . implode(', ', $valuePlaceholders) . ')
            )';
        }

        return [' WHERE ' . implode(' AND ', $conditions), $parameters];
    }

    /** Traduit les statuts métier en conditions SQL. */
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

    /** Construit un ordre stable à partir d'un tri autorisé. */
    private function sortSql(string $sort): string
    {
        // Le prix affiché tient compte de la meilleure promotion active.
        $effectivePrice = 'p.prix_base * (1 - (COALESCE(reduction, 0) / 100))';

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
