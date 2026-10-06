<?php

$token = is_string($token ?? null) ? $token : '';
$resetStatus = is_string($resetStatus ?? null) ? $resetStatus : 'invalid';
$authErrors = is_array($authErrors ?? null) ? $authErrors : [];
$csrfTokenValue = htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$tokenValue = htmlspecialchars($token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>

<section class="account-access" aria-labelledby="password-reset-title">
    <div class="container account-access__container">
        <div class="account-auth-shell" data-motion="section">
            <article class="account-panel">
                <?php if ($resetStatus === 'valid' || $authErrors !== []): ?>
                    <div class="account-panel__heading">
                        <i data-lucide="key-round" aria-hidden="true"></i>
                        <div><h1 id="password-reset-title">Choisissez un nouveau mot de passe</h1><p>Utilisez au moins 8 caractères, une lettre et un chiffre.</p></div>
                    </div>
                    <?php if ($authErrors !== []): ?><div class="account-alert account-alert--error" role="alert"><i data-lucide="circle-alert" aria-hidden="true"></i><ul><?php foreach ($authErrors as $error): ?><li><?= htmlspecialchars((string) $error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></li><?php endforeach; ?></ul></div><?php endif; ?>
                    <form class="account-form" action="/reinitialiser-mot-de-passe/appliquer" method="post">
                        <input type="hidden" name="_token" value="<?= $csrfTokenValue ?>">
                        <input type="hidden" name="token" value="<?= $tokenValue ?>">
                        <div class="account-field"><label for="reset-password">Nouveau mot de passe</label><input id="reset-password" type="password" name="password" autocomplete="new-password" required minlength="8"></div>
                        <div class="account-field"><label for="reset-password-confirmation">Confirmer le mot de passe</label><input id="reset-password-confirmation" type="password" name="password_confirmation" autocomplete="new-password" required minlength="8"></div>
                        <?php $button = ['label' => 'Enregistrer le nouveau mot de passe', 'variant' => 'primary', 'type' => 'submit', 'icon' => 'check']; ?>
                        <?php require dirname(__DIR__) . '/components/button.php'; ?>
                    </form>
                <?php elseif ($resetStatus === 'expired'): ?>
                    <div class="account-panel__heading"><i data-lucide="clock-alert" aria-hidden="true"></i><div><h1 id="password-reset-title">Lien expiré</h1><p>Demandez un nouveau lien de réinitialisation pour continuer.</p></div></div>
                    <a class="account-form__link" href="/mot-de-passe-oublie">Demander un nouveau lien</a>
                <?php else: ?>
                    <div class="account-panel__heading"><i data-lucide="circle-alert" aria-hidden="true"></i><div><h1 id="password-reset-title">Lien invalide</h1><p>Ce lien est incorrect ou n’est plus utilisable.</p></div></div>
                    <a class="account-form__link" href="/mot-de-passe-oublie">Demander un nouveau lien</a>
                <?php endif; ?>
                <p class="account-panel__switch"><a href="/connexion">Retour à la connexion</a></p>
            </article>
        </div>
    </div>
</section>

<?php unset($token, $resetStatus, $authErrors, $csrfTokenValue, $tokenValue, $button); ?>