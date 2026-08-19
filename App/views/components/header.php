<?php $activePage = $activePage ?? ''; ?>

<a class="site-header__skip-link" href="#main-content">Aller au contenu</a>

<header class="site-header" data-header>
    <!-- Barre supérieure -->
    <div class="top-bar">
        <div class="container top-bar__inner">
            <div class="top-bar__highlights" aria-label="Informations commerciales">
                <span>Bienvenue chez Tout y est</span>
                <span class="top-bar__item">
                    <i data-lucide="truck" aria-hidden="true"></i>
                    Livraison disponible
                </span>
                <span class="top-bar__item top-bar__promotion">
                    <i data-lucide="tag" aria-hidden="true"></i>
                    Découvrez nos promotions du moment
                </span>
            </div>

            <div class="top-bar__contacts" aria-label="Contacts et réseaux sociaux">
                <a href="/contact" aria-label="Nous contacter sur WhatsApp">
                    <i data-lucide="message-circle" aria-hidden="true"></i>
                    <span>WhatsApp</span>
                </a>
                <a href="/contact" aria-label="Nous contacter par téléphone">
                    <i data-lucide="phone" aria-hidden="true"></i>
                    <span>Téléphone</span>
                </a>
                <a href="#" aria-label="Facebook">
                    <i data-lucide="thumbs-up" aria-hidden="true"></i>
                </a>
                <a href="#" aria-label="Instagram">
                    <i data-lucide="camera" aria-hidden="true"></i>
                </a>
                <a href="#" aria-label="TikTok">
                    <i data-lucide="music-2" aria-hidden="true"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Barre principale -->
    <div class="header-main">
        <div class="container header-main__inner">
            <div class="header-brand-container">
                <?php require __DIR__ . '/brand.php'; ?>
            </div>

            <form class="header-search" action="/boutique" method="get" role="search">
                <label class="visually-hidden" for="header-search-input">Rechercher un produit</label>
                <input
                    id="header-search-input"
                    type="search"
                    name="q"
                    placeholder="Rechercher un téléphone, un vêtement, une chaussure..."
                    autocomplete="off"
                >
                <button type="submit" aria-label="Lancer la recherche">
                    <i data-lucide="search" aria-hidden="true"></i>
                </button>
            </form>

            <div class="header-actions">
                <a class="header-action header-action--desktop" href="/favoris" aria-label="Favoris, 0 article">
                    <span class="header-action__icon">
                        <i data-lucide="heart" aria-hidden="true"></i>
                        <span class="action-badge" aria-hidden="true">0</span>
                    </span>
                    <span class="header-action__label">Favoris</span>
                </a>
                <a class="header-action header-action--desktop" href="/compte" aria-label="Mon compte">
                    <span class="header-action__icon">
                        <i data-lucide="user" aria-hidden="true"></i>
                    </span>
                    <span class="header-action__label">Mon compte</span>
                </a>
                <a class="header-action header-action--cart" href="/panier" aria-label="Panier, 0 produit">
                    <span class="header-action__icon">
                        <i data-lucide="shopping-cart" aria-hidden="true"></i>
                        <span class="action-badge" aria-hidden="true">0</span>
                    </span>
                    <span class="header-action__label">Panier</span>
                </a>
            </div>
            <button
                class="menu-toggle"
                type="button"
                aria-label="Ouvrir le menu"
                aria-controls="primary-navigation"
                aria-expanded="false"
                data-menu-open
            >
                <i data-lucide="menu" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="site-navigation" id="primary-navigation" aria-label="Navigation principale" data-mobile-menu>
        <div class="site-navigation__mobile-header">
            <?php require __DIR__ . '/brand.php'; ?>
            <button type="button" aria-label="Fermer le menu" data-menu-close>
                <i data-lucide="x" aria-hidden="true"></i>
            </button>
        </div>

        <ul class="container site-navigation__list">
            <li>
                <a class="site-navigation__link<?= $activePage === 'home' ? ' is-active' : '' ?>" href="/"<?= $activePage === 'home' ? ' aria-current="page"' : '' ?>>Accueil</a>
            </li>
            <li>
                <button class="site-navigation__categories<?= $activePage === 'categories' ? ' is-active' : '' ?>" type="button" aria-haspopup="true" aria-expanded="false" data-categories-trigger>
                    <i data-lucide="layout-grid" aria-hidden="true"></i>
                    <span>Catégories</span>
                    <i class="site-navigation__categories-chevron" data-lucide="chevron-down" aria-hidden="true"></i>
                </button>
            </li>
            <li>
                <a class="site-navigation__link<?= $activePage === 'shop' ? ' is-active' : '' ?>" href="/boutique"<?= $activePage === 'shop' ? ' aria-current="page"' : '' ?>>Boutique</a>
            </li>
            <li>
                <a class="site-navigation__link<?= $activePage === 'promotions' ? ' is-active' : '' ?>" href="/promotions"<?= $activePage === 'promotions' ? ' aria-current="page"' : '' ?>>Promotions</a>
            </li>
            <li>
                <a class="site-navigation__link<?= $activePage === 'about' ? ' is-active' : '' ?>" href="/a-propos"<?= $activePage === 'about' ? ' aria-current="page"' : '' ?>>À propos</a>
            </li>
            <li>
                <a class="site-navigation__link<?= $activePage === 'contact' ? ' is-active' : '' ?>" href="/contact"<?= $activePage === 'contact' ? ' aria-current="page"' : '' ?>>Contact</a>
            </li>
            <li class="site-navigation__mobile-action site-navigation__mobile-action--first">
                <a class="site-navigation__link" href="/favoris" aria-label="Favoris, 0 article">
                    <span class="header-action__icon">
                        <i data-lucide="heart" aria-hidden="true"></i>
                        <span class="action-badge" aria-hidden="true">0</span>
                    </span>
                    <span>Favoris</span>
                </a>
            </li>
            <li class="site-navigation__mobile-action">
                <a class="site-navigation__link" href="/compte">
                    <i data-lucide="user" aria-hidden="true"></i>
                    Mon compte
                </a>
            </li>
        </ul>
    </nav>

    <button class="menu-overlay" type="button" aria-label="Fermer le menu" aria-hidden="true" tabindex="-1" data-menu-overlay></button>
</header>
