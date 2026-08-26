<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Category;
use Throwable;

final class CategoryService
{
    public function __construct(
        private readonly ?Category $categoryModel,
        private readonly ProductService $productService,
    ) {
    }

    public function navigation(): array
    {
        return [
            'categories' => $this->categories(),
            'highlights' => $this->highlights(),
        ];
    }

    private function categories(): array
    {
        try {
            $rows = $this->categoryModel?->findActiveHierarchy() ?? [];
        } catch (Throwable) {
            $rows = [];
        }

        if ($rows === []) {
            $catalogCategories = $this->productService->catalogCategories();

            return array_map(static fn (string $name, string $slug): array => [
                'id' => $slug,
                'name' => $name,
                'slug' => $slug,
                'url' => '/boutique?categories%5B%5D=' . rawurlencode($slug),
                'count' => null,
                'children' => [],
            ], $catalogCategories, array_keys($catalogCategories));
        }

        $categories = [];
        $childrenByParent = [];

        foreach ($rows as $row) {
            $id = (int) $row['id_categorie'];
            $slug = (string) $row['slug_'];
            $parentId = isset($row['parent_id']) ? (int) $row['parent_id'] : 0;
            $categories[$id] = [
                'id' => $id,
                'name' => (string) $row['nom'],
                'slug' => $slug,
                'url' => '/boutique?categories%5B%5D=' . rawurlencode($slug),
                'count' => (int) $row['product_count'],
                'children' => [],
            ];
            $childrenByParent[$parentId][] = $id;
        }

        $buildBranch = function (int $id) use (&$buildBranch, $categories, $childrenByParent): array {
            $category = $categories[$id];
            $category['children'] = array_map($buildBranch, $childrenByParent[$id] ?? []);
            $slugs = [$category['slug']];

            foreach ($category['children'] as $child) {
                $slugs[] = $child['slug'];
            }

            $category['url'] = '/boutique?' . http_build_query(['categories' => array_values(array_unique($slugs))]);

            return $category;
        };
        $childIds = array_merge(...array_values(array_filter(
            $childrenByParent,
            static fn (array $ids, int $parentId): bool => $parentId > 0 && isset($categories[$parentId]),
            ARRAY_FILTER_USE_BOTH,
        ))) ?: [];
        $rootIds = array_values(array_diff(array_keys($categories), $childIds));

        return array_map($buildBranch, $rootIds);
    }

    private function highlights(): array
    {
        $groups = [
            ['label' => 'Nouveauté', 'products' => $this->productService->getNewArrivals()],
            ['label' => 'Promotion', 'products' => $this->productService->getPromotionPreview()],
            ['label' => 'Populaire', 'products' => $this->productService->getRecommendations()],
        ];
        $highlights = [];

        foreach ($groups as $group) {
            $product = $group['products'][0] ?? null;

            if (!is_array($product)) {
                continue;
            }

            $highlights[] = [
                'label' => $group['label'],
                'name' => (string) $product['name'],
                'image' => (string) $product['image'],
                'alt' => (string) $product['alt'],
                'url' => (string) $product['url'],
            ];
        }

        return $highlights;
    }
}
