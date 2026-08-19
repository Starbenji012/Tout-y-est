<?php

declare(strict_types=1);

namespace App\Services;

final class ProductService
{
    public function getNewArrivals(): array
    {
        return $this->withBadge(array_slice($this->products(), 0, 8), 'Nouveau', 'new');
    }

    public function getRecommendations(): array
    {
        return $this->withBadge($this->select([9, 2, 10, 6]), 'Populaire', 'popular');
    }

    public function getPromotions(): array
    {
        $products = array_filter(
            $this->products(),
            static fn (array $product): bool => $product['oldPrice'] !== null,
        );

        return $this->withBadge(array_values($products), 'Promotion', 'promotion');
    }

    public function getPromotionPreview(): array
    {
        return array_slice($this->getPromotions(), 0, 4);
    }

    public function getCatalogPreview(): array
    {
        return $this->select([1, 4, 5, 7]);
    }

    public function getCatalog(): array
    {
        return $this->products();
    }

    private function select(array $ids): array
    {
        $productsById = [];

        foreach ($this->products() as $product) {
            $productsById[$product['id']] = $product;
        }

        return array_values(array_filter(array_map(
            static fn (int $id): ?array => $productsById[$id] ?? null,
            $ids,
        )));
    }

    private function withBadge(array $products, string $label, string $variant): array
    {
        return array_map(
            static fn (array $product): array => $product + [
                'badge' => $label,
                'badgeVariant' => $variant,
            ],
            $products,
        );
    }

    private function withDiscounts(array $products): array
    {
        return array_map(
            static function (array $product): array {
                $currentPrice = (int) preg_replace('/\D+/', '', (string) $product['price']);
                $oldPrice = (int) preg_replace('/\D+/', '', (string) ($product['oldPrice'] ?? ''));
                $product['discount'] = $oldPrice > $currentPrice
                    ? (int) round((1 - ($currentPrice / $oldPrice)) * 100)
                    : null;

                return $product;
            },
            $products,
        );
    }

    private function products(): array
    {
        return $this->withDiscounts([
            [
                'id' => 1,
                'name' => 'Montre Signature',
                'category' => 'Accessoires',
                'price' => '32 000 FCFA',
                'oldPrice' => '38 000 FCFA',
                'rating' => 4.8,
                'reviews' => 24,
                'image' => '/assets/images/products/watch.jpg',
                'alt' => 'Montre rectangulaire argentée posée sur une surface réfléchissante',
                'width' => 933,
                'height' => 1400,
                'url' => '/boutique',
            ],
            [
                'id' => 2,
                'name' => 'T-shirt Essential',
                'category' => 'Vêtements',
                'price' => '15 000 FCFA',
                'oldPrice' => null,
                'rating' => 4.6,
                'reviews' => 18,
                'image' => '/assets/images/products/tshirt-card.jpg',
                'alt' => 'T-shirts noir et blanc soigneusement pliés',
                'width' => 1400,
                'height' => 934,
                'url' => '/boutique',
            ],
            [
                'id' => 3,
                'name' => 'Casquette Urbaine',
                'category' => 'Accessoires',
                'price' => '12 500 FCFA',
                'oldPrice' => '15 000 FCFA',
                'rating' => 4.9,
                'reviews' => 31,
                'image' => '/assets/images/products/hat.jpg',
                'alt' => 'Sélection de casquettes présentées en boutique',
                'width' => 1400,
                'height' => 933,
                'url' => '/boutique',
            ],
            [
                'id' => 4,
                'name' => 'Costume Élégance',
                'category' => 'Vêtements',
                'price' => '95 000 FCFA',
                'oldPrice' => null,
                'rating' => 4.5,
                'reviews' => 15,
                'image' => '/assets/images/products/suit.jpg',
                'alt' => 'Costume anthracite avec détail doré sur le revers',
                'width' => 933,
                'height' => 1400,
                'url' => '/boutique',
            ],
            [
                'id' => 5,
                'name' => 'Ordinateur Portable Pro',
                'category' => 'Informatique',
                'price' => '650 000 FCFA',
                'oldPrice' => '700 000 FCFA',
                'rating' => 4.7,
                'reviews' => 12,
                'image' => '/assets/images/products/laptop.jpg',
                'alt' => 'Ordinateur portable utilisé sur un espace de travail',
                'width' => 933,
                'height' => 1400,
                'url' => '/boutique',
            ],
            [
                'id' => 6,
                'name' => 'Téléviseur Smart 4K',
                'category' => 'Électronique',
                'price' => '420 000 FCFA',
                'oldPrice' => null,
                'rating' => 4.4,
                'reviews' => 9,
                'image' => '/assets/images/products/television.jpg',
                'alt' => 'Téléviseur grand écran installé dans un salon moderne',
                'width' => 933,
                'height' => 1400,
                'url' => '/boutique',
            ],
            [
                'id' => 7,
                'name' => 'Manette Gaming Sans Fil',
                'category' => 'Gaming',
                'price' => '55 000 FCFA',
                'oldPrice' => '65 000 FCFA',
                'rating' => 4.6,
                'reviews' => 17,
                'image' => '/assets/images/products/gaming-controller.jpg',
                'alt' => 'Manette de jeu sans fil dans une ambiance gaming',
                'width' => 933,
                'height' => 1400,
                'url' => '/boutique',
            ],
            [
                'id' => 8,
                'name' => 'Bague Cœur Dorée',
                'category' => 'Bijoux',
                'price' => '18 000 FCFA',
                'oldPrice' => null,
                'rating' => 4.8,
                'reviews' => 27,
                'image' => '/assets/images/products/jewelry.jpg',
                'alt' => 'Bagues dorées serties présentées dans un écrin',
                'width' => 1120,
                'height' => 1400,
                'url' => '/boutique',
            ],
            [
                'id' => 9,
                'name' => 'Écouteurs Sans Fil',
                'category' => 'Audio',
                'price' => '28 000 FCFA',
                'oldPrice' => '32 000 FCFA',
                'rating' => 4.7,
                'reviews' => 21,
                'image' => '/assets/images/products/wireless-earbuds.jpg',
                'alt' => 'Sélection d’écouteurs sans fil dans leurs boîtiers',
                'width' => 1400,
                'height' => 788,
                'url' => '/boutique',
            ],
            [
                'id' => 10,
                'name' => 'Casque Audio Confort',
                'category' => 'Audio',
                'price' => '45 000 FCFA',
                'oldPrice' => '52 000 FCFA',
                'rating' => 4.8,
                'reviews' => 19,
                'image' => '/assets/images/products/headphones.jpg',
                'alt' => 'Casque audio beige avec finitions dorées',
                'width' => 1050,
                'height' => 1400,
                'url' => '/boutique',
            ],
        ]);
    }
}
