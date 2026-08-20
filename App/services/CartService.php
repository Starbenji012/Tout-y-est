<?php

declare(strict_types=1);

namespace App\Services;

final class CartService
{
    public function __construct(private readonly ProductService $productService)
    {
    }

    public function buildCart(string $serializedItems): array
    {
        $requestedItems = $this->normalizeItems($serializedItems);
        $products = $this->productService->findProductsByIds(array_keys($requestedItems));
        $items = [];
        $subtotal = 0.0;
        $count = 0;

        foreach ($products as $product) {
            $productId = (int) $product['id'];
            $stock = max(0, (int) ($product['stock'] ?? 0));
            $quantity = min($requestedItems[$productId], $stock > 0 ? $stock : 1);
            $unitPrice = (float) ($product['priceValue'] ?? 0);
            $lineTotal = $unitPrice * $quantity;
            $subtotal += $lineTotal;
            $count += $quantity;
            $items[] = [
                'product' => $product,
                'quantity' => $quantity,
                'available' => $stock > 0,
                'lineTotal' => $this->formatPrice($lineTotal),
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
        ];
    }

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

    private function formatPrice(float $price): string
    {
        return number_format($price, 0, ',', ' ') . ' FCFA';
    }
}
