<?php

// Prépare les deux formulaires d'authentification à partir des données du contrôleur.

$authErrors = $authErrors ?? [];
$oldInput = $oldInput ?? [];
$activeMode = ($oldInput['mode'] ?? '') === 'register' ? 'register' : 'login';
$csrfTokenValue = htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$authAdvice = isset($authAdvice) && is_string($authAdvice) ? $authAdvice : null;
$loginFailures = max(0, (int) ($loginFailures ?? 0));
$showLoginHelp = (bool) ($showLoginHelp ?? false);
$loginRetryAfter = max(0, (int) ($loginRetryAfter ?? 0));
$returnTo = isset($returnTo) && is_string($returnTo) ? $returnTo : '';
?>

<section class="account-access" aria-labelledby="account-access-title" data-auth-view data-active-mode="<?= $activeMode ?>">
    <div class="container account-access__container">
        <header class="account-access__header" data-motion="section">
            <?php $badge = ['label' => 'Espace sécurisé', 'variant' => 'popular']; ?>
            <?php require dirname(__DIR__) . '/components/badge.php'; ?>
            <h1 id="account-access-title">Bienvenue chez Tout y est</h1>
            <p>Un espace simple et sécurisé pour gérer vos achats en toute confiance.</p>
        </header>

        <?php if ($authErrors !== []): ?>
            <div class="account-alert account-alert--error" role="alert" data-motion="section">
                <i data-lucide="circle-alert" aria-hidden="true"></i>
                <div>
                    <strong><?= $activeMode === 'login' ? 'Connexion non aboutie' : 'Vérifiez vos informations' ?></strong>
                    <ul>
                        <?php foreach ($authErrors as $error): ?>
                            <li><?= htmlspecialchars((string) $error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if ($authAdvice !== null): ?>
                        <p><?= htmlspecialchars($authAdvice, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="account-access__layout">
            <aside class="account-benefits" aria-labelledby="account-benefits-title" data-motion="side">
                <span class="account-benefits__eyebrow">Votre compte, simplement</span>
                <h2 id="account-benefits-title">Une expérience plus rapide et plus personnelle</h2>
                <ul>
                    <li><i data-lucide="shield-check" aria-hidden="true"></i><span><strong>Données protégées</strong>Vos informations restent confidentielles.</span></li>
                    <li><i data-lucide="package-check" aria-hidden="true"></i><span><strong>Commandes faciles à suivre</strong>Retrouvez leur progression au même endroit.</span></li>
                    <li><i data-lucide="heart" aria-hidden="true"></i><span><strong>Favoris accessibles</strong>Revenez rapidement aux produits qui vous plaisent.</span></li>
                    <li><i data-lucide="zap" aria-hidden="true"></i><span><strong>Achat plus rapide</strong>Préparez vos prochaines commandes sans effort.</span></li>
                </ul>
            </aside>

            <div class="account-auth-shell" data-motion="section">
                <p class="visually-hidden" aria-live="polite" data-auth-status></p>
                <article class="account-panel" data-auth-panel="login"<?= $activeMode !== 'login' ? ' hidden' : '' ?>>
                    <div class="account-panel__heading">
                        <i data-lucide="log-in" aria-hidden="true"></i>
                        <div><h2>Bon retour !</h2><p>Connectez-vous pour retrouver votre panier, vos favoris et vos commandes.</p></div>
                    </div>

                    <form class="account-form" action="/connexion" method="post" data-auth-form novalidate>
                        <input type="hidden" name="_token" value="<?= $csrfTokenValue ?>">
                        <input type="hidden" name="mode" value="login">
                        <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                        <div class="account-field">
                            <label for="login-email">Adresse e-mail</label>
                            <input id="login-email" type="email" name="email" autocomplete="email" required value="<?= $activeMode === 'login' ? htmlspecialchars((string) ($oldInput['email'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '' ?>" data-validate="email" aria-describedby="login-email-error">
                            <small id="login-email-error" aria-live="polite" data-field-error></small>
                        </div>
                        <div class="account-field">
                            <label for="login-password">Mot de passe</label>
                            <div class="account-password">
                                <input id="login-password" type="password" name="password" autocomplete="current-password" required data-password-input data-validate="required" aria-describedby="login-password-error">
                                <button type="button" aria-label="Afficher le mot de passe" aria-pressed="false" data-password-toggle><i data-lucide="eye" aria-hidden="true"></i></button>
                            </div>
                            <small id="login-password-error" aria-live="polite" data-field-error></small>
                        </div>
                        <div class="account-form__options">
                            <label class="account-checkbox"><input type="checkbox" name="remember" value="1"><span>Se souvenir de moi</span></label>
                            <button class="account-form__link" type="button" data-forgot-password>Mot de passe oublié ?</button>
                        </div>
                        <?php if ($showLoginHelp): ?>
                            <div class="account-login-help" role="status">
                                <i data-lucide="life-buoy" aria-hidden="true"></i>
                                <div>
                                    <strong>Besoin d’aide pour vous connecter ?</strong>
                                    <p>Après <?= $loginFailures ?> tentatives, vérifiez calmement votre adresse ou récupérez votre accès.</p>
                                    <button type="button" data-forgot-password>Utiliser « Mot de passe oublié ? »</button>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ($loginRetryAfter > 0): ?>
                            <p class="account-retry" aria-live="polite" data-auth-retry="<?= $loginRetryAfter ?>">Nouvelle tentative disponible dans <?= $loginRetryAfter ?> secondes.</p>
                        <?php endif; ?>
                        <?php $button = ['label' => 'Se connecter', 'variant' => 'primary', 'type' => 'submit', 'icon' => 'arrow-right', 'attributes' => ['data-auth-submit' => true, 'data-loading-label' => 'Connexion…']]; ?>
                        <?php require dirname(__DIR__) . '/components/button.php'; ?>
                    </form>

                    <div class="account-provider">
                        <span>ou</span>
                        <button class="account-provider__button" type="button" aria-describedby="google-auth-status" data-google-auth>
                            <span aria-hidden="true">G</span>
                            Se connecter avec Google
                        </button>
                        <small id="google-auth-status">Activation après configuration sécurisée du fournisseur Google.</small>
                    </div>

                    <p class="account-panel__switch">Vous n’avez pas encore de compte ? <button type="button" data-auth-switch="register">Créer un compte</button></p>
                </article>

                <article class="account-panel" data-auth-panel="register"<?= $activeMode !== 'register' ? ' hidden' : '' ?>>
                    <div class="account-panel__heading">
                        <i data-lucide="user-plus" aria-hidden="true"></i>
                        <div><h2>Bienvenue chez Tout y est !</h2><p>Créez votre compte en quelques instants pour une expérience d’achat simple et personnalisée.</p></div>
                    </div>

                    <form class="account-form account-form--registration" action="/connexion" method="post" data-auth-form novalidate>
                        <input type="hidden" name="_token" value="<?= $csrfTokenValue ?>">
                        <input type="hidden" name="mode" value="register">
                        <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                        <div class="account-field">
                            <label for="register-last-name">Nom</label>
                            <input id="register-last-name" type="text" name="nom" autocomplete="family-name" required maxlength="100" value="<?= htmlspecialchars((string) ($oldInput['nom'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-validate="name" aria-describedby="register-last-name-error">
                            <small id="register-last-name-error" aria-live="polite" data-field-error></small>
                        </div>
                        <div class="account-field">
                            <label for="register-first-name">Prénom</label>
                            <input id="register-first-name" type="text" name="prenom" autocomplete="given-name" required maxlength="100" value="<?= htmlspecialchars((string) ($oldInput['prenom'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-validate="name" aria-describedby="register-first-name-error">
                            <small id="register-first-name-error" aria-live="polite" data-field-error></small>
                        </div>
                        <div class="account-field account-field--full">
                            <label for="register-email">Adresse e-mail</label>
                            <input id="register-email" type="email" name="email" autocomplete="email" required maxlength="254" value="<?= $activeMode === 'register' ? htmlspecialchars((string) ($oldInput['email'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '' ?>" data-validate="email" aria-describedby="register-email-error">
                            <small id="register-email-error" aria-live="polite" data-field-error></small>
                        </div>
                        <div class="account-field account-field--full">
                            <label for="register-password">Mot de passe</label>
                            <div class="account-password"><input id="register-password" type="password" name="password" autocomplete="new-password" required minlength="8" data-password-input data-register-password data-validate="password" aria-describedby="register-password-error register-password-strength"><button type="button" aria-label="Afficher le mot de passe" aria-pressed="false" data-password-toggle><i data-lucide="eye" aria-hidden="true"></i></button></div>
                            <small id="register-password-error" aria-live="polite" data-field-error></small>
                            <div class="account-password-strength" id="register-password-strength" aria-live="polite" data-password-strength><span aria-hidden="true"></span><small>Utilisez 8 caractères, une lettre et un chiffre.</small></div>
                        </div>
                        <div class="account-field account-field--full">
                            <label for="register-password-confirmation">Confirmer le mot de passe</label>
                            <div class="account-password"><input id="register-password-confirmation" type="password" name="password_confirmation" autocomplete="new-password" required minlength="8" data-password-input data-password-confirmation data-validate="confirmation" data-match="#register-password" aria-describedby="register-password-confirmation-error"><button type="button" aria-label="Afficher la confirmation du mot de passe" aria-pressed="false" data-password-toggle><i data-lucide="eye" aria-hidden="true"></i></button></div>
                            <small id="register-password-confirmation-error" aria-live="polite" data-field-error></small>
                        </div>
                        <?php $button = ['label' => 'Créer mon compte', 'variant' => 'primary', 'type' => 'submit', 'icon' => 'user-plus', 'attributes' => ['data-auth-submit' => true, 'data-loading-label' => 'Création…']]; ?>
                        <?php require dirname(__DIR__) . '/components/button.php'; ?>
                    </form>

                    <p class="account-panel__switch">Vous avez déjà un compte ? <button type="button" data-auth-switch="login">Se connecter</button></p>
                </article>
            </div>
        </div>
    </div>
</section>

<?php unset($authErrors, $oldInput, $activeMode, $csrfTokenValue, $authAdvice, $loginFailures, $showLoginHelp, $loginRetryAfter, $returnTo, $error, $badge, $button); ?>
