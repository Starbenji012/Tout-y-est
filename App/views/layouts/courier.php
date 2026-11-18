<?php $escapeLayout=static fn(mixed $value):string=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); ?>
<!DOCTYPE html>
<html lang="fr"><head>
 <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
 <meta name="csrf-token" content="<?= $escapeLayout($csrfToken) ?>">
 <title><?= $escapeLayout($title??'Mes livraisons') ?> | Tout y est</title>
 <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
 <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
 <?php foreach(['variables','reset','global','utilities','courier-tours'] as $style): ?><link rel="stylesheet" href="/assets/css/<?= $style ?>.css"><?php endforeach; ?>
</head><body class="courier-body">
 <a class="courier-skip" href="#main-content">Aller aux livraisons</a>
 <header class="courier-header"><div class="container courier-header__inner">
  <?php require dirname(__DIR__).'/components/brand.php'; ?>
  <span class="courier-header__label">Espace Livreur</span><a class="btn btn-outline" href="/compte">Mon compte</a>
 </div></header>
 <main id="main-content"><?= $content??'' ?></main><script src="/assets/js/courier-tours.js"></script>
</body></html>
