<section class="cart-checkout-gate" aria-labelledby="cart-checkout-gate-title" data-cart-checkout-gate hidden>
    <ol class="cart-progress" aria-label="Progression de la commande">
        <li class="is-complete"><i data-lucide="check" aria-hidden="true"></i><span>Panier</span></li>
        <li aria-current="step"><span>2</span><strong>Identification</strong></li>
        <li><span>3</span><span>Livraison</span></li>
        <li><span>4</span><span>Paiement</span></li>
    </ol>

    <div class="cart-checkout-gate__content">
        <span class="cart-checkout-gate__icon"><i data-lucide="user-round-check" aria-hidden="true"></i></span>
        <p class="cart-checkout-gate__eyebrow">Étape suivante</p>
        <h2 id="cart-checkout-gate-title" tabindex="-1">Vous êtes presque arrivé&nbsp;!</h2>
        <p>Connectez-vous ou créez un compte pour finaliser votre commande.</p>

        <div class="cart-checkout-gate__actions">
            <?php
            $button = [
                'label' => 'Continuer avec mon compte',
                'variant' => 'primary',
                'href' => '/connexion?return=/panier',
                'icon' => 'log-in',
                'iconPosition' => 'start',
            ];
            require __DIR__ . '/button.php';
            ?>
            <?php
            $button = [
                'label' => 'Créer un compte',
                'variant' => 'secondary',
                'href' => '/connexion?return=/panier#inscription',
                'icon' => 'user-plus',
                'iconPosition' => 'start',
            ];
            require __DIR__ . '/button.php';
            ?>
        </div>

        <p class="cart-checkout-gate__reassurance"><i data-lucide="lock-keyhole" aria-hidden="true"></i>Votre panier sera conservé.</p>
        <button class="btn btn-ghost" type="button" data-cart-gate-back><i data-lucide="arrow-left" aria-hidden="true"></i>Retour au panier</button>
    </div>
</section>

<?php unset($button); ?>
