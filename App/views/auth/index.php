<?php

// Prépare les deux formulaires d'authentification à partir des données du contrôleur.

$authErrors = $authErrors ?? [];
$fieldErrors = is_array($fieldErrors ?? null) ? $fieldErrors : [];
$oldInput = $oldInput ?? [];
$activeMode = ($oldInput['mode'] ?? '') === 'register' ? 'register' : 'login';
$csrfTokenValue = htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$authAdvice = isset($authAdvice) && is_string($authAdvice) ? $authAdvice : null;
$loginFailures = max(0, (int) ($loginFailures ?? 0));
$showLoginHelp = (bool) ($showLoginHelp ?? false);
$loginRetryAfter = max(0, (int) ($loginRetryAfter ?? 0));
$returnTo = isset($returnTo) && is_string($returnTo) ? $returnTo : '';
$resetSuccess = (bool) ($resetSuccess ?? false);
?>

<section class="account-access" aria-label="Connexion et inscription" data-auth-view data-active-mode="<?= $activeMode ?>">
    <div class="container account-access__container">
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

        <?php if ($resetSuccess): ?>
            <div class="account-alert account-alert--success" role="status" data-motion="section">
                <i data-lucide="badge-check" aria-hidden="true"></i>
                <div><strong>Mot de passe mis à jour</strong><p>Vous pouvez maintenant vous connecter avec votre nouveau mot de passe.</p></div>
            </div>
        <?php endif; ?>

        <div class="account-access__layout">
            <div class="account-auth-shell" data-motion="section">
                <p class="visually-hidden" aria-live="polite" data-auth-status></p>
                <article class="account-panel" data-auth-panel="login"<?= $activeMode !== 'login' ? ' hidden' : '' ?>>
                    <div class="account-panel__heading">
                        <h1>Bon retour !</h1>
                        <p>Connectez-vous pour retrouver votre panier, vos favoris et vos commandes.</p>
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
                            <a class="account-form__link" href="/mot-de-passe-oublie">Mot de passe oublié ?</a>
                        </div>
                        <?php if ($showLoginHelp): ?>
                            <div class="account-login-help" role="status">
                                <i data-lucide="life-buoy" aria-hidden="true"></i>
                                <div>
                                    <strong>Besoin d’aide pour vous connecter ?</strong>
                                    <p>Après <?= $loginFailures ?> tentatives, vérifiez calmement votre adresse ou récupérez votre accès.</p>
                                    <a href="/mot-de-passe-oublie">Utiliser « Mot de passe oublié ? »</a>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ($loginRetryAfter > 0): ?>
                            <p class="account-retry" aria-live="polite" data-auth-retry="<?= $loginRetryAfter ?>">Nouvelle tentative disponible dans <?= $loginRetryAfter ?> secondes.</p>
                        <?php endif; ?>
                        <?php $button = ['label' => 'Se connecter', 'variant' => 'primary', 'type' => 'submit', 'icon' => 'arrow-right', 'attributes' => ['data-auth-submit' => true, 'data-loading-label' => 'Connexion…']]; ?>
                        <?php require dirname(__DIR__) . '/components/button.php'; ?>
                    </form>

                    <p class="account-panel__switch">Vous n’avez pas encore de compte ? <button type="button" data-auth-switch="register">Créer un compte</button></p>
                </article>

                <article class="account-panel" data-auth-panel="register"<?= $activeMode !== 'register' ? ' hidden' : '' ?>>
                    <div class="account-panel__heading">
                        <h1>Bienvenue chez Tout y est !</h1>
                        <p>Créez votre compte en quelques instants pour une expérience d’achat simple et personnalisée.</p>
                    </div>

                    <form class="account-form account-form--registration" action="/connexion" method="post" data-auth-form novalidate>
                        <input type="hidden" name="_token" value="<?= $csrfTokenValue ?>">
                        <input type="hidden" name="mode" value="register">
                        <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                        <div class="account-field">
                            <label for="register-last-name">Nom</label>
                            <input id="register-last-name" type="text" name="nom" autocomplete="family-name" required maxlength="100" value="<?= htmlspecialchars((string) ($oldInput['nom'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-validate="name" aria-describedby="register-last-name-error" aria-invalid="<?= isset($fieldErrors['nom']) ? 'true' : 'false' ?>"<?= isset($fieldErrors['nom']) ? ' class="is-invalid"' : '' ?>>
                            <small id="register-last-name-error" aria-live="polite" data-field-error<?= isset($fieldErrors['nom']) ? ' data-validation-state="error"' : '' ?>><?= htmlspecialchars((string) ($fieldErrors['nom'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></small>
                        </div>
                        <div class="account-field">
                            <label for="register-first-name">Prénom</label>
                            <input id="register-first-name" type="text" name="prenom" autocomplete="given-name" required maxlength="100" value="<?= htmlspecialchars((string) ($oldInput['prenom'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-validate="name" aria-describedby="register-first-name-error" aria-invalid="<?= isset($fieldErrors['prenom']) ? 'true' : 'false' ?>"<?= isset($fieldErrors['prenom']) ? ' class="is-invalid"' : '' ?>>
                            <small id="register-first-name-error" aria-live="polite" data-field-error<?= isset($fieldErrors['prenom']) ? ' data-validation-state="error"' : '' ?>><?= htmlspecialchars((string) ($fieldErrors['prenom'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></small>
                        </div>
                        <div class="account-field account-field--full">
                            <label for="register-email">Adresse e-mail</label>
                            <input id="register-email" type="email" name="email" autocomplete="email" required maxlength="254" value="<?= $activeMode === 'register' ? htmlspecialchars((string) ($oldInput['email'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '' ?>" data-validate="email" aria-describedby="register-email-error" aria-invalid="<?= isset($fieldErrors['email']) ? 'true' : 'false' ?>"<?= isset($fieldErrors['email']) ? ' class="is-invalid"' : '' ?>>
                            <small id="register-email-error" aria-live="polite" data-field-error<?= isset($fieldErrors['email']) ? ' data-validation-state="error"' : '' ?>><?= htmlspecialchars((string) ($fieldErrors['email'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></small>
                        </div>
                        <div class="account-field account-field--full">
                            <label for="register-phone">Téléphone</label>
                            <input id="register-phone" type="tel" name="telephone" autocomplete="tel" inputmode="tel" required maxlength="24" placeholder="+243 81 234 5678" value="<?= htmlspecialchars((string) ($oldInput['telephone'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-validate="phone" aria-describedby="register-phone-hint register-phone-error" aria-invalid="<?= isset($fieldErrors['telephone']) ? 'true' : 'false' ?>"<?= isset($fieldErrors['telephone']) ? ' class="is-invalid"' : '' ?>>
                            <small class="account-form__hint" id="register-phone-hint">Formats acceptés : +243…, 243… ou 0…</small>
                            <small id="register-phone-error" aria-live="polite" data-field-error<?= isset($fieldErrors['telephone']) ? ' data-validation-state="error"' : '' ?>><?= htmlspecialchars((string) ($fieldErrors['telephone'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></small>
                        </div>
                        <div class="account-field account-field--full">
                            <label for="register-password">Mot de passe</label>
                            <div class="account-password"><input id="register-password" type="password" name="password" autocomplete="new-password" required minlength="8" data-password-input data-register-password data-validate="password" aria-describedby="register-password-error register-password-strength" aria-invalid="<?= isset($fieldErrors['password']) ? 'true' : 'false' ?>"<?= isset($fieldErrors['password']) ? ' class="is-invalid"' : '' ?>><button type="button" aria-label="Afficher le mot de passe" aria-pressed="false" data-password-toggle><i data-lucide="eye" aria-hidden="true"></i></button></div>
                            <small id="register-password-error" aria-live="polite" data-field-error<?= isset($fieldErrors['password']) ? ' data-validation-state="error"' : '' ?>><?= htmlspecialchars((string) ($fieldErrors['password'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></small>
                            <div class="account-password-strength" id="register-password-strength" aria-live="polite" data-password-strength><span aria-hidden="true"></span><small>Utilisez 8 caractères, une lettre et un chiffre.</small></div>
                        </div>
                        <div class="account-field account-field--full">
                            <label for="register-password-confirmation">Confirmer le mot de passe</label>
                            <div class="account-password"><input id="register-password-confirmation" type="password" name="password_confirmation" autocomplete="new-password" required minlength="8" data-password-input data-password-confirmation data-validate="confirmation" data-match="#register-password" aria-describedby="register-password-confirmation-error" aria-invalid="<?= isset($fieldErrors['password_confirmation']) ? 'true' : 'false' ?>"<?= isset($fieldErrors['password_confirmation']) ? ' class="is-invalid"' : '' ?>><button type="button" aria-label="Afficher la confirmation du mot de passe" aria-pressed="false" data-password-toggle><i data-lucide="eye" aria-hidden="true"></i></button></div>
                            <small id="register-password-confirmation-error" aria-live="polite" data-field-error<?= isset($fieldErrors['password_confirmation']) ? ' data-validation-state="error"' : '' ?>><?= htmlspecialchars((string) ($fieldErrors['password_confirmation'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></small>
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

<?php unset($authErrors, $fieldErrors, $oldInput, $activeMode, $csrfTokenValue, $authAdvice, $loginFailures, $showLoginHelp, $loginRetryAfter, $returnTo, $resetSuccess, $error, $button); ?>
