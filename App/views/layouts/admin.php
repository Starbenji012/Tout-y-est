<?php $adminUser = \App\Core\Session::get('user'); ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(\App\Middleware\CsrfMiddleware::token(), ENT_QUOTES, 'UTF-8') ?>">
    <title><?= htmlspecialchars($title ?? 'Administration | Tout y est', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php foreach (['variables', 'reset', 'global', 'animations', 'utilities', 'admin'] as $style): ?>
        <link rel="stylesheet" href="/assets/css/<?= $style ?>.css">
    <?php endforeach; ?>
    <?php foreach ($pageStyles ?? [] as $style): ?><link rel="stylesheet" href="<?= htmlspecialchars($style, ENT_QUOTES, 'UTF-8') ?>"><?php endforeach; ?>
</head>
<body class="admin-body">
    <a class="admin-skip-link" href="#main-content">Aller au contenu</a>
    <header class="admin-header">
        <div class="container admin-header__inner">
            <div class="admin-header__brand">
                <?php require dirname(__DIR__) . '/components/brand.php'; ?>
                <span class="admin-header__label">Administration</span>
            </div>
            <nav class="admin-header__actions" aria-label="Navigation administration">
                <a class="admin-header__shop" href="/boutique"><i data-lucide="arrow-up-right" aria-hidden="true"></i>Voir la boutique</a>
                <a class="admin-header__account" href="/compte" aria-label="Mon compte">
                    <?php $avatarName = is_array($adminUser) ? ($adminUser['name'] ?? '') : ''; $avatarSize = 'compact'; require dirname(__DIR__) . '/components/user-avatar.php'; ?>
                    <span>Mon compte</span>
                </a>
            </nav>
        </div>
    </header>
    <main id="main-content" class="admin-main"><?= $content ?? '' ?></main>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>window.lucide?.createIcons();</script>
    <?php foreach ($pageScripts ?? [] as $script): ?><script src="<?= htmlspecialchars($script, ENT_QUOTES, 'UTF-8') ?>"></script><?php endforeach; ?>
</body>
</html>

