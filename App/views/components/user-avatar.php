<?php
// Une initiale suffit en V1 ; le conteneur pourra accueillir une photo plus tard.
$avatarName = trim((string) ($avatarName ?? ''));
preg_match('/^\X/u', $avatarName, $avatarMatch);
$avatarInitial = $avatarMatch[0] ?? '?';
$avatarInitial = function_exists('mb_strtoupper') ? mb_strtoupper($avatarInitial, 'UTF-8') : strtoupper($avatarInitial);
$avatarClass = ($avatarSize ?? '') === 'large' ? 'user-avatar--large' : 'user-avatar--compact';
?>
<span class="user-avatar <?= $avatarClass ?>" role="img" aria-label="<?= htmlspecialchars('Avatar de ' . ($avatarName ?: 'votre compte'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <span class="user-avatar__media" aria-hidden="true"><?= htmlspecialchars($avatarInitial, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
</span>
