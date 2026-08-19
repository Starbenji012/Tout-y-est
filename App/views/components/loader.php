<?php

$loaderLabel = (string) (($loader ?? [])['label'] ?? 'Chargement en cours');
?>

<div class="loader" role="status" aria-label="<?= htmlspecialchars($loaderLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <span class="loader__dot" aria-hidden="true"></span>
    <span class="loader__dot" aria-hidden="true"></span>
    <span class="loader__dot" aria-hidden="true"></span>
</div>

<?php unset($loader, $loaderLabel); ?>
