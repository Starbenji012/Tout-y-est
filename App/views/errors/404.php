<?php

$emptyState = [
    'title' => 'Cette page est introuvable',
    'text' => 'Le contenu demandé n’existe plus ou son adresse a changé.',
    'action' => ['label' => 'Retour à la boutique', 'variant' => 'primary', 'href' => '/boutique'],
];
?>

<section class="section" aria-label="Page introuvable">
    <div class="container">
        <?php require dirname(__DIR__) . '/components/empty-state.php'; ?>
    </div>
</section>

<?php unset($emptyState); ?>
