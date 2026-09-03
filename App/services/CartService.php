<?php

declare(strict_types=1);

namespace App\Services;

/** Transforme les articles mémorisés côté client en un panier fiable. */
final class CartService
{
    /** Reçoit le service produit utilisé pour vérifier les prix et disponibilités. */
    public function __construct(private readonly ProductService $productService)
    {
    }

    /** Reconstruit le panier à partir des données sérialisées du navigateur. */
    public function buildCart(string $serializedItems): array
    {
        $requestedItems = $this->normalizeItems($serializedItems);
        $products = $this->productService->findProductsByIds(array_keys($requestedItems));
        $items = [];
        $subtotal = 0.0;
        $count = 0;
        $quantityAdjusted = false;

        foreach ($products as $product) {
            $productId = (int) $product['id'];
            $stock = max(0, (int) ($product['stock'] ?? 0));
            $quantity = min($requestedItems[$productId], $stock > 0 ? $stock : 1);
            $available = $stock > 0;
            $unitPrice = (float) ($product['priceValue'] ?? 0);
            $availability = $this->availability($stock);
            $quantityAdjusted = $quantityAdjusted || $quantity !== $requestedItems[$productId];
            $subtotal += $available ? $unitPrice * $quantity : 0.0;
            $count += $quantity;
            $items[] = [
                'product' => $product,
                'quantity' => $quantity,
                'available' => $available,
                'availability' => $availability,
            ];
        }

        return [
            'items' => $items,
            'count' => $count,
            'subtotal' => $this->formatPrice($subtotal),
            'total' => $this->formatPrice($subtotal),
            'canCheckout' => $items !== [] && array_reduce(
                $items,
                static fn (bool $available, array $item): bool => $available && $item['available'],
                true,
            ),
            'storedItems' => array_map(
                static fn (array $item): array => [
                    'id' => (int) $item['product']['id'],
                    'quantity' => (int) $item['quantity'],
                ],
                $items,
            ),
            'notice' => $quantityAdjusted
                ? 'Certaines quantités ont été ajustées selon le stock disponible.'
                : null,
        ];
    }

    /** Prépare un message de stock précis uniquement lorsqu'il reste cinq articles ou moins. */
    private function availability(int $stock): array
    {
        if ($stock < 1) {
            return ['state' => 'unavailable', 'label' => 'Rupture de stock', 'icon' => 'circle-x'];
        }

        if ($stock <= 5) {
            return [
                'state' => 'limited',
                'label' => 'Plus que ' . $stock . ' disponible' . ($stock > 1 ? 's' : ''),
                'icon' => 'triangle-alert',
            ];
        }

        return ['state' => 'available', 'label' => 'En stock', 'icon' => 'circle-check'];
    }

    /** Ignore les lignes invalides et normalise identifiants et quantités. */
    private function normalizeItems(string $serializedItems): array
    {
        $items = [];

        foreach (array_slice(explode(',', $serializedItems), 0, 40) as $serializedItem) {
            if (!preg_match('/^(\d+):(\d+)$/', trim($serializedItem), $matches)) {
                continue;
            }

            $productId = (int) $matches[1];
            $quantity = min(99, max(1, (int) $matches[2]));

            if ($productId > 0) {
                $items[$productId] = $quantity;
            }
        }

        return $items;
    }

    /** Formate un montant de manière cohérente avec les cartes produits. */
    private function formatPrice(float $price): string
    {
        return number_format($price, 0, ',', ' ') . ' FCFA';
    }
}
