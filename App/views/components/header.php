<?php
$activePage = $activePage ?? '';
$siteContactLinks = array_values(array_filter(
    is_array($siteContactLinks ?? null) ? $siteContactLinks : [],
    static fn (mixed $link): bool => is_array($link)
        && trim((string) ($link['label'] ?? '')) !== ''
        && trim((string) ($link['href'] ?? '')) !== '',
));
$siteSocialLinks = array_values(array_filter(
    is_array($siteSocialLinks ?? null) ? $siteSocialLinks : [],
    static fn (mixed $link): bool => is_array($link)
        && trim((string) ($link['label'] ?? '')) !== ''
        && trim((string) ($link['href'] ?? '')) !== '',
));
?>

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

            <?php if ($siteContactLinks !== [] || $siteSocialLinks !== []): ?>
                <div class="top-bar__contacts" aria-label="Contacts et réseaux sociaux">
                    <?php foreach ([...$siteContactLinks, ...$siteSocialLinks] as $siteLink): ?>
                        <?php $siteLinkIcon = preg_match('/^[a-z0-9-]+$/', (string) ($siteLink['icon'] ?? '')) ? (string) $siteLink['icon'] : 'link'; ?>
                        <a href="<?= htmlspecialchars((string) $siteLink['href'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" aria-label="<?= htmlspecialchars((string) $siteLink['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                            <i data-lucide="<?= $siteLinkIcon ?>" aria-hidden="true"></i>
                            <span><?= htmlspecialchars((string) $siteLink['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Barre principale -->
    <div class="header-main">
        <div class="container header-main__inner">
            <div class="header-brand-container">
                <?php require __DIR__ . '/brand.php'; ?>
            </div>

            <form class="header-search" action="/boutique" method="get" role="search" data-header-search>
                <label class="visually-hidden" for="header-search-input">Rechercher un produit</label>
                <input
                    id="header-search-input"
                    type="search"
                    name="q"
                    placeholder="Rechercher un produit, une marque, une catégorie..."
                    autocomplete="off"
                    role="combobox"
                    aria-autocomplete="list"
                    aria-controls="header-search-suggestions"
                    aria-expanded="false"
                >
                <button type="submit" aria-label="Lancer la recherche">
                    <i data-lucide="search" aria-hidden="true"></i>
                </button>
                <div class="header-search__suggestions" id="header-search-suggestions" role="listbox" aria-label="Suggestions de produits" data-search-suggestions hidden></div>
            </form>

            <div class="header-actions">
                <a class="header-action header-action--desktop<?= $activePage === 'favorites' ? ' is-active' : '' ?>" href="/favoris" aria-label="Favoris, 0 article" data-favorites-link<?= $activePage === 'favorites' ? ' aria-current="page"' : '' ?>>
                    <span class="header-action__icon">
                        <i data-lucide="heart" aria-hidden="true"></i>
                        <span class="action-badge" aria-hidden="true" data-favorites-count>0</span>
                    </span>
                    <span class="header-action__label">Favoris</span>
                </a>
                <a class="header-action header-action--desktop<?= $activePage === 'account' ? ' is-active' : '' ?>" href="/compte" aria-label="Mon compte"<?= $activePage === 'account' ? ' aria-current="page"' : '' ?>>
                    <span class="header-action__icon">
                        <i data-lucide="user" aria-hidden="true"></i>
                    </span>
                    <span class="header-action__label">Mon compte</span>
                </a>
                <a class="header-action header-action--cart<?= $activePage === 'cart' ? ' is-active' : '' ?>" href="/panier" aria-label="Panier, 0 produit" data-cart-link<?= $activePage === 'cart' ? ' aria-current="page"' : '' ?>>
                    <span class="header-action__icon">
                        <i data-lucide="shopping-cart" aria-hidden="true"></i>
                        <span class="action-badge" aria-hidden="true" data-cart-count>0</span>
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
                <button class="site-navigation__categories<?= $activePage === 'categories' ? ' is-active' : '' ?>" type="button" aria-haspopup="true" aria-controls="category-navigation" aria-expanded="false" data-categories-trigger>
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
                <a class="site-navigation__link" href="/#footer-about">À propos</a>
            </li>
            <li class="site-navigation__mobile-action site-navigation__mobile-action--first">
                <a class="site-navigation__link<?= $activePage === 'favorites' ? ' is-active' : '' ?>" href="/favoris" aria-label="Favoris, 0 article" data-favorites-link<?= $activePage === 'favorites' ? ' aria-current="page"' : '' ?>>
                    <span class="header-action__icon">
                        <i data-lucide="heart" aria-hidden="true"></i>
                        <span class="action-badge" aria-hidden="true" data-favorites-count>0</span>
                    </span>
                    <span>Favoris</span>
                </a>
            </li>
            <li class="site-navigation__mobile-action">
                <a class="site-navigation__link<?= $activePage === 'account' ? ' is-active' : '' ?>" href="/compte"<?= $activePage === 'account' ? ' aria-current="page"' : '' ?>>
                    <i data-lucide="user" aria-hidden="true"></i>
                    Mon compte
                </a>
            </li>
        </ul>
        <?php require __DIR__ . '/category-navigation.php'; ?>
    </nav>

    <button class="menu-overlay" type="button" aria-label="Fermer le menu" aria-hidden="true" tabindex="-1" data-menu-overlay></button>
</header>
