<?php

$verificationStatus = is_string($verificationStatus ?? null) ? $verificationStatus : 'invalid';
$verificationContent = [
    'verified' => ['badge-check', 'Adresse e-mail vérifiée', 'Votre adresse e-mail est maintenant confirmée. Vous pouvez continuer à utiliser votre compte.'],
    'expired' => ['clock-alert', 'Lien expiré', 'Ce lien a expiré. Connectez-vous à votre compte pour demander un nouveau message.'],
    'csrf_error' => ['shield-alert', 'Session expirée', 'Rechargez votre espace compte avant de demander un nouveau message.'],
    'method_not_allowed' => ['circle-alert', 'Action non disponible', 'Utilisez le bouton présent dans votre espace compte.'],
    'unavailable' => ['server-off', 'Vérification indisponible', 'Le service de vérification est temporairement indisponible. Réessayez plus tard.'],
    'invalid' => ['circle-alert', 'Lien invalide', 'Ce lien est incorrect, a déjà été utilisé ou a été remplacé par un lien plus récent.'],
];
[$verificationIcon, $verificationTitle, $verificationText] = $verificationContent[$verificationStatus]
    ?? $verificationContent['invalid'];
$verificationTarget = is_array(\App\Core\Session::get('user')) ? '/compte' : '/connexion';
?>

<section class="account-verification-result" aria-labelledby="verification-result-title">
    <div class="container">
        <article class="empty-state" data-motion="section">
            <div class="empty-state__illustration" aria-hidden="true"><i data-lucide="<?= $verificationIcon ?>"></i></div>
            <h1 class="empty-state__title" id="verification-result-title"><?= htmlspecialchars($verificationTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>
            <p class="empty-state__description"><?= htmlspecialchars($verificationText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
            <?php $button = ['label' => is_array(\App\Core\Session::get('user')) ? 'Retour à mon compte' : 'Se connecter', 'variant' => 'primary', 'href' => $verificationTarget, 'icon' => 'arrow-right']; ?>
            <?php require dirname(__DIR__) . '/components/button.php'; ?>
        </article>
    </div>
</section>

<?php unset($verificationStatus, $verificationContent, $verificationIcon, $verificationTitle, $verificationText, $verificationTarget, $button); ?>
