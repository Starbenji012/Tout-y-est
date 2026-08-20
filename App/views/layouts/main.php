<?php
$pageLibraries = $pageLibraries ?? [];
$usesSwiper = in_array('swiper', $pageLibraries, true);
$usesSweetAlert = in_array('sweetalert2', $pageLibraries, true);
$usesGsap = in_array('gsap', $pageLibraries, true);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= htmlspecialchars($metaDescription ?? 'Tout y est, votre boutique en ligne.', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <meta name="keywords" content="<?= htmlspecialchars($metaKeywords ?? 'boutique en ligne, e-commerce, Tout y est', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

    <title><?= htmlspecialchars($title ?? 'Tout y est', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></title>

    <!-- Icône du site -->
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($favicon ?? '/assets/images/branding/favicon.png', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

    <!-- Typographie -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Design System -->
    <link rel="stylesheet" href="/assets/css/variables.css">
    <link rel="stylesheet" href="/assets/css/reset.css">
    <link rel="stylesheet" href="/assets/css/global.css">
    <link rel="stylesheet" href="/assets/css/animations.css">
    <link rel="stylesheet" href="/assets/css/utilities.css">
    <link rel="stylesheet" href="/assets/css/header.css">
    <link rel="stylesheet" href="/assets/css/footer.css">

    <!-- Bibliothèques CSS -->
    <?php if ($usesSwiper): ?>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@12/swiper-bundle.min.css">
    <?php endif; ?>

    <!-- Styles de la page -->
    <?php foreach ($pageStyles ?? [] as $stylesheet): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($stylesheet, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <?php endforeach; ?>
</head>
<body>
    <!-- En-tête -->
    <?php require dirname(__DIR__) . '/components/header.php'; ?>

    <!-- Contenu -->
    <main id="main-content">
        <?= $content ?? '' ?>
    </main>

    <div data-quick-view-host></div>

    <!-- Pied de page -->
    <?php require dirname(__DIR__) . '/components/footer.php'; ?>

    <!-- Bibliothèques JavaScript -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <?php if ($usesSwiper): ?>
        <script src="https://cdn.jsdelivr.net/npm/swiper@12/swiper-bundle.min.js"></script>
    <?php endif; ?>
    <?php if ($usesSweetAlert): ?>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php endif; ?>
    <?php if ($usesGsap): ?>
        <script src="https://cdn.jsdelivr.net/npm/gsap@3/dist/gsap.min.js"></script>
    <?php endif; ?>

    <script src="/assets/js/favorites-store.js"></script>
    <script src="/assets/js/cart-store.js"></script>
    <script src="/assets/js/animations.js"></script>
    <script src="/assets/js/app.js"></script>
    <script src="/assets/js/header.js"></script>
    <script src="/assets/js/search.js"></script>
    <?php foreach ($pageScripts ?? [] as $script): ?>
        <script src="<?= htmlspecialchars($script, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></script>
    <?php endforeach; ?>
</body>
</html>
