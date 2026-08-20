<?php

$accountUser = $accountUser ?? [];
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

        <div class="account-dashboard__grid">
            <article class="account-dashboard__card" data-motion="card">
                <i data-lucide="contact" aria-hidden="true"></i>
                <h2>Mes informations</h2>
                <dl>
                    <div><dt>E-mail</dt><dd><?= htmlspecialchars((string) ($accountUser['email'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dd></div>
                    <div><dt>Téléphone</dt><dd><?= htmlspecialchars((string) ($accountUser['phone'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dd></div>
                </dl>
            </article>
            <a class="account-dashboard__card" href="/favoris" data-motion="card"><i data-lucide="heart" aria-hidden="true"></i><h2>Mes favoris</h2><p>Retrouvez les produits enregistrés sur cet appareil.</p><span>Voir mes favoris <i data-lucide="arrow-right" aria-hidden="true"></i></span></a>
            <a class="account-dashboard__card" href="/panier" data-motion="card"><i data-lucide="shopping-cart" aria-hidden="true"></i><h2>Mon panier</h2><p>Reprenez rapidement votre sélection en cours.</p><span>Voir mon panier <i data-lucide="arrow-right" aria-hidden="true"></i></span></a>
        </div>

        <form class="account-dashboard__logout" action="/deconnexion" method="post">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <?php $button = ['label' => 'Se déconnecter', 'variant' => 'ghost', 'type' => 'submit', 'icon' => 'log-out', 'iconPosition' => 'start']; ?>
            <?php require dirname(__DIR__) . '/components/button.php'; ?>
        </form>
    </div>
</section>

<?php unset($accountUser, $button); ?>
