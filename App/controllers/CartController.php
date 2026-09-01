<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\CartService;

/** Fournit les vues du panier sans contenir les règles de calcul. */
final class CartController extends Controller
{
    /** Injecte le service qui construit le contenu du panier. */
    public function __construct(
        private readonly CartService $cartService,
        private readonly Request $request,
    ) {
    }

    /** Affiche la page complète du panier. */
    public function index(): void
    {
        $this->render('cart/index', [
            'title' => 'Mon panier | Tout y est',
            'metaDescription' => 'Vérifiez les produits sélectionnés dans votre panier Tout y est.',
            'activePage' => 'cart',
            'pageLibraries' => ['gsap', 'sweetalert2'],
            'pageStyles' => ['/assets/css/cart.css'],
            'pageScripts' => ['/assets/js/cart.js'],
        ]);
    }

    /** Retourne seulement le fragment actualisable par Fetch API. */
    public function content(): void
    {
        $cart = $this->cartService->buildCart((string) ($this->request->queryParameters()['items'] ?? ''));

        Response::json([
            'html' => $this->renderPartial('components/cart-content', ['cart' => $cart]),
            'count' => $cart['count'],
            'items' => $cart['storedItems'],
        ]);
    }
}
