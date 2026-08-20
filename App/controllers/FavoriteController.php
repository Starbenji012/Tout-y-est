<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class FavoriteController extends Controller
{
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
}
