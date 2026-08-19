<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class HomeController extends Controller
{
    public function index(): void
    {
        $this->render('home/index', [
            'title' => 'Accueil | Tout y est',
            'metaDescription' => 'Découvrez Tout y est, votre boutique en ligne.',
            'activePage' => 'home',
            'pageLibraries' => ['swiper'],
            'pageStyles' => ['/assets/css/hero.css', '/assets/css/nouveautes.css'],
            'pageScripts' => ['/assets/js/hero.js', '/assets/js/nouveautes.js'],
        ]);
    }
}
