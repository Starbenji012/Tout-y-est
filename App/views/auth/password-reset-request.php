<?php

$authErrors = is_array($authErrors ?? null) ? $authErrors : [];
$resetSubmitted = (bool) ($resetSubmitted ?? false);
$csrfTokenValue = htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>

<section class="account-access" aria-labelledby="password-reset-request-title">
    <div class="container account-access__container">
        <div class="account-auth-shell" data-motion="section">
            <article class="account-panel">
                <div class="account-panel__heading">
                    <i data-lucide="key-round" aria-hidden="true"></i>
                    <div><h1 id="password-reset-request-title">Mot de passe oublié ?</h1><p>Indiquez votre adresse e-mail pour recevoir un lien sécurisé.</p></div>
                </div>
                <?php if ($authErrors !== []): ?>
                    <div class="account-alert account-alert--error" role="alert"><i data-lucide="circle-alert" aria-hidden="true"></i><ul><?php foreach ($authErrors as $error): ?><li><?= htmlspecialchars((string) $error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></li><?php endforeach; ?></ul></div>
                <?php endif; ?>
                <?php if ($resetSubmitted): ?>
                    <div class="account-alert account-alert--success" role="status"><i data-lucide="mail-check" aria-hidden="true"></i><p>Si un compte correspond à cette adresse, un lien de réinitialisation a été envoyé.</p></div>
                <?php endif; ?>
                <form class="account-form" action="/mot-de-passe-oublie" method="post">
                    <input type="hidden" name="_token" value="<?= $csrfTokenValue ?>">
                    <div class="account-field">
                        <label for="reset-email">Adresse e-mail</label>
                        <input id="reset-email" type="email" name="email" autocomplete="email" required maxlength="254">
                    </div>
                    <?php $button = ['label' => 'Recevoir le lien', 'variant' => 'primary', 'type' => 'submit', 'icon' => 'send']; ?>
                    <?php require dirname(__DIR__) . '/components/button.php'; ?>
                </form>
                <p class="account-panel__switch"><a href="/connexion">Retour à la connexion</a></p>
            </article>
        </div>
    </div>
</section>

<?php unset($authErrors, $resetSubmitted, $csrfTokenValue, $button); ?>