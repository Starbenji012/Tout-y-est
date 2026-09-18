<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Favorite;

/** Applique les règles métier communes aux favoris invités et connectés. */
final class FavoriteService
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly ?Favorite $favoriteModel = null,
    ) {
    }

    /** Indique si la persistance utilisateur est disponible. */
    public function isAvailable(): bool
    {
        return $this->favoriteModel !== null;
    }

    /** Retourne la sélection personnelle du compte. */
    public function productIdsForUser(int $userId): array
    {
        if (!$this->validUser($userId)) {
            return [];
        }

        return $this->favoriteModel->productIdsForUser($userId);
    }

    /** Ajoute un produit actif à la sélection personnelle. */
    public function add(int $userId, int $productId): bool
    {
        if (!$this->validUser($userId) || !$this->isUsableProduct($productId)) {
            return false;
        }

        $this->favoriteModel->add($userId, $productId);

        return true;
    }

    /** Retire un produit sans toucher aux favoris des autres comptes. */
    public function remove(int $userId, int $productId): bool
    {
        if (!$this->validUser($userId) || $productId < 1) {
            return false;
        }

        $this->favoriteModel->remove($userId, $productId);

        return true;
    }

    /** Fusionne les favoris invités utilisables avec ceux déjà en base. */
    public function mergeGuestFavorites(int $userId, string $serializedIds): bool
    {
        if (!$this->validUser($userId)) {
            return false;
        }

        $guestIds = $this->normalizeIds(explode(',', $serializedIds));
        $validProducts = $this->productService->findProductsByIds($guestIds);
        $validIds = array_map(
            static fn (array $product): int => (int) ($product['id'] ?? 0),
            $validProducts,
        );

        $this->favoriteModel->merge($userId, $validIds);

        return true;
    }

    /** Nettoie une sélection invitée avant son affichage. */
    public function guestProductIds(string $serializedIds): array
    {
        $products = $this->productService->findProductsByIds(
            $this->normalizeIds(explode(',', $serializedIds)),
        );

        return array_map(
            static fn (array $product): int => (int) ($product['id'] ?? 0),
            $products,
        );
    }

    /** Vérifie le compte sans accepter son identité depuis le navigateur. */
    private function validUser(int $userId): bool
    {
        return $this->favoriteModel !== null
            && $userId > 0
            && $this->favoriteModel->userExists($userId);
    }

    /** Vérifie que le produit peut encore être présenté à l'utilisateur. */
    private function isUsableProduct(int $productId): bool
    {
        return $productId > 0
            && $this->productService->findProductsByIds([$productId]) !== [];
    }

    /** Conserve au maximum quarante identifiants positifs et uniques. */
    private function normalizeIds(array $values): array
    {
        return array_values(array_unique(array_slice(array_filter(
            array_map(static fn (mixed $value): int => (int) $value, $values),
            static fn (int $value): bool => $value > 0,
        ), 0, 40)));
    }
}
