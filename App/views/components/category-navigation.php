<?php // Structure vide que JavaScript remplit avec les catégories reçues de l'API. ?>
<div class="category-navigation" id="category-navigation" data-category-navigation hidden>
    <div class="container category-navigation__panel">
        <p class="category-navigation__status" role="status" data-category-navigation-status>
            Chargement des catégories…
        </p>
        <div class="category-navigation__content" data-category-navigation-content hidden>
            <section class="category-navigation__categories" aria-labelledby="category-navigation-title">
                <h2 id="category-navigation-title">Explorer les catégories</h2>
                <ul data-category-roots></ul>
            </section>
            <section class="category-navigation__highlights" aria-labelledby="category-highlights-title">
                <h2 id="category-highlights-title">À découvrir</h2>
                <p class="visually-hidden" role="status" data-category-highlights-status></p>
                <div data-category-highlights></div>
            </section>
        </div>
        <a class="category-navigation__all" href="/boutique">
            Voir toute la boutique
            <i data-lucide="arrow-right" aria-hidden="true"></i>
        </a>
    </div>
</div>
