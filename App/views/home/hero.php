<?php

$heroSlides = $heroSlides ?? [
    [
        'badge' => 'Nouvelle collection',
        'titleLead' => 'Tout ce dont',
        'titleAccent' => 'vous avez besoin,',
        'titleEnd' => 'au même endroit.',
        'description' => 'Découvrez une sélection pensée pour votre quotidien, avec des produits utiles, actuels et accessibles.',
        'image' => '/assets/images/products/jeans.jpg',
        'alt' => 'Collection de jeans suspendus dans une boutique',
        'width' => 933,
        'height' => 1400,
        'imageClass' => 'hero-product-image--collection',
    ],
    [
        'badge' => 'Sélection du moment',
        'titleLead' => 'Un style simple,',
        'titleAccent' => 'pensé pour vous,',
        'titleEnd' => 'chaque jour.',
        'description' => 'Des essentiels faciles à porter, choisis pour associer confort, sobriété et modernité.',
        'image' => '/assets/images/products/jewelry.jpg',
        'alt' => 'Sélection de bagues dorées présentées dans un écrin',
        'width' => 1120,
        'height' => 1400,
        'imageClass' => 'hero-product-image--jewelry',
    ],
    [
        'badge' => 'À découvrir',
        'titleLead' => 'Des produits choisis',
        'titleAccent' => 'avec attention,',
        'titleEnd' => 'pour vous accompagner.',
        'description' => 'Explorez des nouveautés variées dans une boutique claire, pratique et proche de vos besoins.',
        'image' => '/assets/images/products/shoes.jpg',
        'alt' => 'Chaussure bordeaux présentée sur un fond doré',
        'width' => 960,
        'height' => 1400,
        'imageClass' => 'hero-product-image--shoes',
    ],
];
?>

<section class="hero" aria-labelledby="hero-title" data-hero>
    <div class="hero-wrapper">
        <div class="swiper hero-slider" id="hero-carousel" data-hero-slider>
            <div class="swiper-wrapper">
                <?php foreach ($heroSlides as $index => $slide): ?>
                    <?php $headingTag = $index === 0 ? 'h1' : 'h2'; ?>
                    <article class="swiper-slide hero-slide">
                        <div class="hero-slide__layout">
                            <div class="hero-left">
                                <?php $badge = ['label' => $slide['badge'], 'variant' => 'new', 'class' => 'section-badge']; ?>
                                <?php require dirname(__DIR__) . '/components/badge.php'; ?>
                                <<?= $headingTag ?> class="hero-title"<?= $index === 0 ? ' id="hero-title"' : '' ?>>
                                    <span><?= htmlspecialchars($slide['titleLead'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                                    <strong class="hero-title__accent"><?= htmlspecialchars($slide['titleAccent'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>
                                    <span><?= htmlspecialchars($slide['titleEnd'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                                </<?= $headingTag ?>>
                                <p class="hero-description"><?= htmlspecialchars($slide['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                                <div class="hero-buttons">
                                    <?php $button = ['label' => 'Découvrir la boutique', 'variant' => 'primary', 'href' => '/boutique', 'icon' => 'arrow-right']; ?>
                                    <?php require dirname(__DIR__) . '/components/button.php'; ?>
                                    <?php $button = ['label' => 'Voir les promotions', 'variant' => 'secondary', 'href' => '/promotions']; ?>
                                    <?php require dirname(__DIR__) . '/components/button.php'; ?>
                                </div>
                                <a class="hero-scroll-cue" href="#new-products-title" aria-label="Découvrir les nouveautés">
                                    <span>Découvrir la suite</span>
                                    <i data-lucide="chevron-down" aria-hidden="true"></i>
                                </a>
                            </div>

                            <div class="hero-right">
                                <div class="hero-product-showcase">
                                    <img
                                        class="hero-product-image <?= htmlspecialchars($slide['imageClass'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                                        src="<?= htmlspecialchars($slide['image'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                                        alt="<?= htmlspecialchars($slide['alt'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                                        width="<?= (int) $slide['width'] ?>"
                                        height="<?= (int) $slide['height'] ?>"
                                        <?= $index === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>
                                    >
                                </div>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="hero-navigation" aria-label="Navigation du carrousel">
            <button class="hero-navigation__button hero-navigation__previous" type="button" aria-label="Diapositive précédente">
                <i data-lucide="arrow-left" aria-hidden="true"></i>
            </button>
            <div class="hero-pagination" aria-label="Choisir une diapositive"></div>
            <button class="hero-navigation__button hero-navigation__next" type="button" aria-label="Diapositive suivante">
                <i data-lucide="arrow-right" aria-hidden="true"></i>
            </button>
        </div>
    </div>
</section>
