<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Cart;

/** Transforme les articles mémorisés côté client en un panier fiable. */
final class CartService
{
    /** Reçoit les dépendances du panier invité et du panier connecté. */
    public function __construct(
        private readonly ProductService $productService,
        private readonly ?Cart $connectedCart = null,
    )
    {
    }

    /** Reconstruit le panier connecté depuis les lignes persistées et le catalogue courant. */
    public function buildConnectedCart(int $userId): array
    {
        if ($this->connectedCart === null || $userId < 1) {
            return $this->emptyCart();
        }

        $cartId = $this->connectedCart->findOrCreateForUser($userId);
        $requestedItems = [];

        foreach ($this->connectedCart->lines($cartId) as $line) {
            $variantId = (int) ($line['variant_id'] ?? 0);
            $quantity = min(99, max(1, (int) ($line['quantity'] ?? 0)));

            if ($variantId > 0 && $quantity > 0) {
                $requestedItems[$variantId] = $quantity;
            }
        }

        return $this->buildFromRequestedItems($requestedItems);
    }

    /** Fusionne une seule fois le panier invité avec le panier persistant. */
    public function mergeGuestCart(int $userId, string $serializedItems): bool
    {
        if ($this->connectedCart === null || $userId < 1) {
            return false;
        }

        $cartId = $this->connectedCart->findOrCreateForUser($userId);
        if ($cartId < 1) {
            return false;
        }

        $guestItems = $this->normalizeItems($serializedItems);
        $connectedItems = [];
        foreach ($this->connectedCart->lines($cartId) as $line) {
            $variantId = (int) ($line['variant_id'] ?? 0);
            $quantity = min(99, max(1, (int) ($line['quantity'] ?? 0)));
            if ($variantId > 0 && $quantity > 0) {
                $connectedItems[$variantId] = $quantity;
            }
        }

        $combinedItems = $connectedItems;
        foreach ($guestItems as $variantId => $quantity) {
            $combinedItems[$variantId] = min(99, ($combinedItems[$variantId] ?? 0) + $quantity);
        }

        $products = $this->productService->findProductsByVariantIds(array_keys($combinedItems));
        $validQuantities = [];
        foreach ($products as $product) {
            $variantId = (int) ($product['variantId'] ?? 0);
            $stock = max(0, (int) ($product['stock'] ?? 0));
            if ($variantId < 1 || $stock < 1 || !isset($combinedItems[$variantId])) {
                continue;
            }
            $validQuantities[$variantId] = min($combinedItems[$variantId], $stock);
        }

        $this->connectedCart->mergeLines($cartId, $validQuantities);

        return true;
    }

    /** Reconstruit le panier à partir des données sérialisées du navigateur. */
    public function buildCart(string $serializedItems): array
    {
        $requestedItems = $this->normalizeItems($serializedItems);
        return $this->buildFromRequestedItems($requestedItems);
    }

    /** Recalcule prix, promotions, stock et totaux depuis les variantes actuelles. */
    private function buildFromRequestedItems(array $requestedItems): array
    {
        $products = $this->productService->findProductsByVariantIds(array_keys($requestedItems));
        $items = [];
        $subtotal = 0.0;
        $count = 0;
        $quantityAdjusted = false;

        foreach ($products as $product) {
            $variantId = (int) ($product['variantId'] ?? $product['id']);
            if (!isset($requestedItems[$variantId])) {
                continue;
            }
            $stock = max(0, (int) ($product['stock'] ?? 0));
            $quantity = min($requestedItems[$variantId], $stock > 0 ? $stock : 1);
            $available = $stock > 0;
            $unitPrice = (float) ($product['priceValue'] ?? 0);
            $availability = $this->availability($stock);
            $quantityAdjusted = $quantityAdjusted || $quantity !== $requestedItems[$variantId];
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
                    'id' => (int) ($item['product']['variantId'] ?? $item['product']['id']),
                    'variantId' => (int) ($item['product']['variantId'] ?? $item['product']['id']),
                    'quantity' => (int) $item['quantity'],
                ],
                $items,
            ),
            'notice' => $quantityAdjusted
                ? 'Certaines quantités ont été ajustées selon le stock disponible.'
                : null,
        ];
    }

    /** Retourne une structure stable lorsque le panier connecté est indisponible. */
    private function emptyCart(): array
    {
        return [
            'items' => [],
            'count' => 0,
            'subtotal' => $this->formatPrice(0),
            'total' => $this->formatPrice(0),
            'canCheckout' => false,
            'storedItems' => [],
            'notice' => null,
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
