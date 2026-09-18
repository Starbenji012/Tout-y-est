<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Middleware\CsrfMiddleware;
use App\Services\FavoriteService;
use App\Services\ProductService;
use PDOException;

/** Coordonne la page et les API des favoris sans porter la logique métier. */
final class FavoriteController extends Controller
{
    public function __construct(
        private readonly FavoriteService $favoriteService,
        private readonly ProductService $productService,
        private readonly Request $request,
    ) {
    }

    /** Rend la page des favoris, alimentée ensuite par son API. */
    public function index(): void
    {
        $this->render('account/favorites', [
            'title' => 'Mes favoris | Tout y est',
            'metaDescription' => 'Retrouvez les produits enregistrés dans vos favoris Tout y est.',
            'activePage' => 'favorites',
            'pageLibraries' => ['gsap', 'sweetalert2'],
            'pageStyles' => ['/assets/css/product-section.css'],
            'pageScripts' => ['/assets/js/product-section.js', '/assets/js/favorites.js'],
        ]);
    }

    /** Retourne les cartes favorites adaptées au visiteur ou au compte connecté. */
    public function content(): void
    {
        $userId = $this->currentUserId();

        if ($userId > 0 && !$this->favoriteService->isAvailable()) {
            Response::json(['error' => 'Les favoris sont temporairement indisponibles.'], 503);
            return;
        }

        try {
            $ids = $userId > 0
                ? $this->favoriteService->productIdsForUser($userId)
                : $this->favoriteService->guestProductIds(
                    (string) ($this->request->queryParameters()['ids'] ?? ''),
                );
            $products = $this->productService->findProductsByIds($ids);
        } catch (PDOException) {
            Response::json(['error' => 'Impossible de charger les favoris.'], 503);
            return;
        }

        Response::json([
            'html' => $this->renderPartial('components/product-results', [
                'productResults' => [
                    'products' => $products,
                    'context' => 'favorites',
                    'emptyState' => [
                        'title' => 'Vos favoris vous attendent',
                        'text' => 'Ajoutez des produits à votre sélection pour les retrouver facilement ici.',
                        'action' => ['label' => 'Découvrir la boutique', 'variant' => 'primary', 'href' => '/boutique'],
                    ],
                ],
            ]),
            'count' => count($products),
            'ids' => array_map(static fn (array $product): int => (int) $product['id'], $products),
        ]);
    }

    /** Retourne l'état léger utilisé par le compteur et les cœurs. */
    public function state(): void
    {
        $userId = $this->currentUserId();

        if ($userId < 1) {
            Response::json(['error' => 'Authentification requise.'], 401);
            return;
        }

        if (!$this->favoriteService->isAvailable()) {
            Response::json(['error' => 'Les favoris sont temporairement indisponibles.'], 503);
            return;
        }

        try {
            $ids = $this->favoriteService->productIdsForUser($userId);
        } catch (PDOException) {
            Response::json(['error' => 'Impossible de charger les favoris.'], 503);
            return;
        }

        Response::json(['count' => count($ids), 'ids' => $ids]);
    }

    /** Ajoute ou retire un favori après validation de la session et du CSRF. */
    public function mutate(): void
    {
        $userId = $this->currentUserId();
        $input = $this->request->postParameters();

        if ($userId < 1) {
            Response::json(['error' => 'Authentification requise.'], 401);
            return;
        }

        if (!CsrfMiddleware::isValid($input['_token'] ?? null)) {
            Response::json(['error' => 'Votre session a expiré.'], 419);
            return;
        }

        if (!$this->favoriteService->isAvailable()) {
            Response::json(['error' => 'Les favoris sont temporairement indisponibles.'], 503);
            return;
        }

        $action = (string) ($input['action'] ?? '');
        $productId = (int) ($input['productId'] ?? 0);

        try {
            $success = match ($action) {
                'add' => $this->favoriteService->add($userId, $productId),
                'remove' => $this->favoriteService->remove($userId, $productId),
                default => false,
            };
        } catch (PDOException) {
            Response::json(['error' => 'Impossible d’enregistrer ce favori.'], 503);
            return;
        }

        if (!$success) {
            Response::json(['error' => 'Produit indisponible ou mutation invalide.'], 422);
            return;
        }

        try {
            $ids = $this->favoriteService->productIdsForUser($userId);
        } catch (PDOException) {
            Response::json(['error' => 'Favori enregistré, mais son état ne peut pas être relu.'], 503);
            return;
        }

        Response::json([
            'active' => in_array($productId, $ids, true),
            'count' => count($ids),
            'ids' => $ids,
        ]);
    }

    /** Fusionne une sélection locale sans jamais recevoir l'identité du compte. */
    public function merge(): void
    {
        $userId = $this->currentUserId();
        $input = $this->request->postParameters();

        if ($userId < 1) {
            Response::json(['error' => 'Authentification requise.'], 401);
            return;
        }

        if (!CsrfMiddleware::isValid($input['_token'] ?? null)) {
            Response::json(['error' => 'Votre session a expiré.'], 419);
            return;
        }

        try {
            $merged = $this->favoriteService->mergeGuestFavorites(
                $userId,
                (string) ($input['ids'] ?? ''),
            );
        } catch (PDOException) {
            Response::json(['error' => 'Impossible de fusionner les favoris.'], 503);
            return;
        }

        if (!$merged) {
            Response::json(['error' => 'Fusion des favoris indisponible.'], 422);
            return;
        }

        try {
            $ids = $this->favoriteService->productIdsForUser($userId);
        } catch (PDOException) {
            Response::json(['error' => 'Fusion terminée, mais son état ne peut pas être relu.'], 503);
            return;
        }

        Response::json(['count' => count($ids), 'ids' => $ids]);
    }

    /** Lit l'identité uniquement depuis la session serveur. */
    private function currentUserId(): int
    {
        $user = Session::get('user');

        return is_array($user) ? (int) ($user['id'] ?? 0) : 0;
    }
}
