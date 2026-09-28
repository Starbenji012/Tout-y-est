<?php

$accountUser = $accountUser ?? [];
$emailVerification = is_array($emailVerification ?? null) ? $emailVerification : [];
$emailVerificationNotice = is_string($emailVerificationNotice ?? null) ? $emailVerificationNotice : null;
$verificationMessages = [
    'sent' => ['success', 'Un nouveau lien de vérification a été envoyé.'],
    'cooldown' => ['info', 'Un lien vient déjà d’être demandé. Patientez une minute avant de recommencer.'],
    'already_verified' => ['success', 'Votre adresse e-mail est déjà vérifiée.'],
    'transport_unavailable' => ['warning', 'L’envoi réel reste à configurer sur cet environnement.'],
    'send_failed' => ['warning', 'Le message n’a pas pu être envoyé. Réessayez plus tard.'],
    'unavailable' => ['warning', 'La vérification est temporairement indisponible.'],
];
$verificationMessage = $emailVerificationNotice !== null
    ? ($verificationMessages[$emailVerificationNotice] ?? null)
    : null;
?>

<section class="account-dashboard" aria-labelledby="account-dashboard-title">
    <div class="container account-dashboard__container">
        <header class="account-dashboard__welcome" data-motion="section">
            <span class="account-dashboard__icon" aria-hidden="true"><i data-lucide="user-round"></i></span>
            <div>
                <span>Votre espace personnel</span>
                <h1 id="account-dashboard-title">Bonjour, <?= htmlspecialchars((string) ($accountUser['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>
                <p>Retrouvez ici les informations utiles à votre expérience Tout y est.</p>
            </div>
        </header>

        <?php if (!($emailVerification['verified'] ?? false)): ?>
            <section class="account-verification" aria-labelledby="account-verification-title" data-motion="section">
                <i data-lucide="mail-warning" aria-hidden="true"></i>
                <div class="account-verification__content">
                    <h2 id="account-verification-title">Votre adresse e-mail n’est pas encore vérifiée.</h2>
                    <p>Vérifiez votre boîte de réception pour confirmer que cette adresse vous appartient.</p>
                    <?php if (is_array($verificationMessage)): ?>
                        <p class="account-verification__message is-<?= htmlspecialchars($verificationMessage[0], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" role="status">
                            <?= htmlspecialchars($verificationMessage[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                        </p>
                    <?php endif; ?>
                </div>
                <form action="/verification-email/renvoyer" method="post">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                    <?php $button = ['label' => 'Renvoyer l’e-mail', 'variant' => 'secondary', 'type' => 'submit', 'icon' => 'send']; ?>
                    <?php require dirname(__DIR__) . '/components/button.php'; ?>
                </form>
            </section>
        <?php elseif ($emailVerificationNotice === 'already_verified'): ?>
            <p class="account-verification-confirmed" role="status"><i data-lucide="badge-check" aria-hidden="true"></i>Votre adresse e-mail est vérifiée.</p>
        <?php endif; ?>

        <div class="account-dashboard__grid">
            <article class="account-dashboard__card" data-motion="card">
                <i data-lucide="contact" aria-hidden="true"></i>
                <h2>Mes informations</h2>
                <dl>
                    <div><dt>E-mail</dt><dd><?= htmlspecialchars((string) ($accountUser['email'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dd></div>
                    <?php if (!empty($accountUser['phone'])): ?>
                        <div><dt>Téléphone</dt><dd><?= htmlspecialchars((string) $accountUser['phone'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dd></div>
                    <?php endif; ?>
                </dl>
            </article>
            <a class="account-dashboard__card" href="/favoris" data-motion="card"><i data-lucide="heart" aria-hidden="true"></i><h2>Mes favoris</h2><p>Retrouvez les produits enregistrés dans votre compte.</p><span>Voir mes favoris <i data-lucide="arrow-right" aria-hidden="true"></i></span></a>
            <a class="account-dashboard__card" href="/panier" data-motion="card"><i data-lucide="shopping-cart" aria-hidden="true"></i><h2>Mon panier</h2><p>Reprenez rapidement votre sélection en cours.</p><span>Voir mon panier <i data-lucide="arrow-right" aria-hidden="true"></i></span></a>
        </div>

        <form class="account-dashboard__logout" action="/deconnexion" method="post">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <?php $button = ['label' => 'Se déconnecter', 'variant' => 'ghost', 'type' => 'submit', 'icon' => 'log-out', 'iconPosition' => 'start']; ?>
            <?php require dirname(__DIR__) . '/components/button.php'; ?>
        </form>
    </div>
</section>

<?php unset($accountUser, $emailVerification, $emailVerificationNotice, $verificationMessages, $verificationMessage, $button); ?>
