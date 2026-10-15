<?php
$profile = $checkout['profile'];
$addresses = $checkout['addresses'];
$zones = $checkout['zones'];
$cart = $checkout['cart'];
$summary = null;
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$sectionHeader = ['id' => 'checkout-title', 'headingLevel' => 1, 'badge' => ['label' => 'Votre livraison', 'variant' => 'popular'], 'title' => 'Informations & livraison', 'description' => 'Préparez votre livraison en toute simplicité.'];
?>
<section class="checkout-page" aria-labelledby="checkout-title" data-checkout-page>
    <div class="container">
        <?php $button = ['label' => 'Retour au panier', 'variant' => 'ghost', 'href' => '/panier', 'icon' => 'arrow-left', 'iconPosition' => 'start']; require dirname(__DIR__) . '/components/button.php'; ?>
        <?php require dirname(__DIR__) . '/components/section-header.php'; ?>
        <ol class="checkout-progress" aria-label="Progression du checkout">
            <li aria-current="step"><strong>1. Informations & livraison</strong></li>
            <li>2. Paiement <small>Prochainement</small></li>
            <li>3. Confirmation <small>Prochainement</small></li>
        </ol>
        <div class="checkout-layout">
            <div class="checkout-information">
                <section class="checkout-panel checkout-account" aria-labelledby="checkout-account-title">
                    <div class="checkout-account__information">
                    <h2 id="checkout-account-title">Votre compte</h2>
                    <p><?= $escape($profile['prenom']) ?> <?= $escape($profile['nom']) ?></p>
                    <p><?= $escape($profile['email']) ?></p>
                    <?php if ($profile['telephone']): ?><p><?= $escape($profile['telephone']) ?></p><?php endif; ?>
                    <?php if ($profile['email_verified_at'] === null): ?>
                        <p class="checkout-help">Votre e-mail devra être vérifié avant la finalisation de la commande. <a href="/compte">Vérifier mon adresse e-mail</a></p>
                    <?php else: ?><p class="checkout-help">Adresse e-mail vérifiée.</p><?php endif; ?>
                    </div>
                    <div class="checkout-account__avatar">
                        <?php $avatarName = trim($profile['prenom']) ?: $profile['nom']; $avatarSize = 'large'; require dirname(__DIR__) . '/components/user-avatar.php'; ?>
                    </div>
                </section>
                <form class="checkout-panel" action="/api/checkout/livraison" method="post" data-checkout-form>
                    <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
                    <fieldset class="checkout-address-choice">
                        <legend>Adresse de livraison</legend>
                        <?php foreach ($addresses as $address): ?>
                            <label class="checkout-saved-address">
                                <input type="radio" name="address_id" value="<?= (int) $address['id_adresse'] ?>" data-address="<?= $escape(json_encode($address, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)) ?>">
                                <span><strong><?= $escape($address['commune']) ?><?= $address['adresse_par_defaut'] ? ' — Par défaut' : '' ?></strong><br><?= $escape($address['adresse']) ?><small><?= $escape($address['prenom_destinataire'] . ' ' . $address['nom_destinataire']) ?></small></span>
                            </label>
                        <?php endforeach; ?>
                        <label class="checkout-saved-address"><input type="radio" name="address_id" value="0" checked><span>Utiliser une nouvelle adresse</span></label>
                    </fieldset>
                    <div class="checkout-fields" data-address-fields>
                        <?php foreach (['prenom_destinataire' => ['Prénom du destinataire', $profile['prenom'], 'given-name'], 'nom_destinataire' => ['Nom du destinataire', $profile['nom'], 'family-name'], 'telephone' => ['Téléphone', $profile['telephone'], 'tel'], 'commune' => ['Commune', '', 'address-level2']] as $name => [$label, $value, $autocomplete]): ?>
                            <div class="checkout-field">
                                <label for="checkout-<?= $name ?>"><?= $label ?></label>
                                <input id="checkout-<?= $name ?>" name="<?= $name ?>" type="<?= $name === 'telephone' ? 'tel' : 'text' ?>" autocomplete="<?= $autocomplete ?>" value="<?= $escape($value) ?>" maxlength="<?= $name === 'telephone' ? 30 : 100 ?>" required aria-describedby="error-<?= $name ?>">
                                <small class="checkout-error" id="error-<?= $name ?>" data-error-for="<?= $name ?>"></small>
                            </div>
                        <?php endforeach; ?>
                        <div class="checkout-field checkout-field--wide">
                            <label for="checkout-zone">Endroit / zone de livraison</label>
                            <div class="form-select"><select id="checkout-zone" name="id_zone_livraison" required <?= $zones === [] ? 'disabled' : '' ?> aria-describedby="error-id_zone_livraison checkout-zones-notice">
                                <option value="">Choisissez une zone</option>
                                <?php foreach ($zones as $zone): ?><option value="<?= (int) $zone['id_zone_livraison'] ?>"><?= $escape($zone['nom']) ?></option><?php endforeach; ?>
                            </select></div>
                            <small class="checkout-error" id="error-id_zone_livraison" data-error-for="id_zone_livraison"></small>
                            <p class="checkout-help" id="checkout-zones-notice"><?= $zones === [] ? 'Les zones et frais de livraison seront bientôt disponibles. Vous pourrez poursuivre lorsqu’une zone réelle sera configurée.' : 'Le tarif est calculé par le serveur selon la zone choisie.' ?></p>
                        </div>
                        <?php foreach (['adresse' => ['Adresse précise', 255, true], 'repere' => ['Repère (facultatif)', 255, false], 'instructions' => ['Instructions de livraison (facultatif)', 1000, false]] as $name => [$label, $max, $required]): ?>
                            <div class="checkout-field checkout-field--wide"><label for="checkout-<?= $name ?>"><?= $label ?></label>
                                <textarea id="checkout-<?= $name ?>" name="<?= $name ?>" rows="2" maxlength="<?= $max ?>" <?= $required ? 'required' : '' ?> aria-describedby="error-<?= $name ?>"></textarea>
                                <small class="checkout-error" id="error-<?= $name ?>" data-error-for="<?= $name ?>"></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="checkout-save-options" data-save-options>
                        <label class="form-check"><input type="checkbox" name="save_address" value="1"> Enregistrer cette adresse pour mes prochaines commandes</label>
                        <label class="form-check"><input type="checkbox" name="make_default" value="1"> Utiliser comme adresse par défaut</label>
                    </div>
                    <p role="status" aria-live="polite" tabindex="-1" data-checkout-message></p>
                    <?php $button = ['label' => 'Valider les informations de livraison', 'type' => 'submit', 'variant' => 'secondary', 'attributes' => ['disabled' => $zones === [], 'aria-describedby' => 'checkout-zones-notice']]; require dirname(__DIR__) . '/components/button.php'; ?>
                    <noscript><p>Activez JavaScript pour valider les informations et actualiser le récapitulatif sans rechargement.</p></noscript>
                </form>
            </div>
            <aside class="checkout-panel checkout-summary" aria-labelledby="checkout-summary-title">
                <div data-checkout-summary><?php require __DIR__ . '/summary.php'; ?></div>
                <form action="/api/checkout/commande" method="post" data-order-form>
                    <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
                    <input type="hidden" name="_creation_key" value="<?= $escape($creationKey ?? '') ?>">
                    <?php $button = ['label' => 'Continuer au paiement', 'type' => 'submit', 'variant' => 'primary', 'attributes' => ['disabled' => true, 'data-order-submit' => true, 'aria-describedby' => 'checkout-payment-notice']]; require dirname(__DIR__) . '/components/button.php'; ?>
                    <p role="status" aria-live="polite" tabindex="-1" data-order-message></p>
                </form>
                <p class="checkout-help" id="checkout-payment-notice"><?= $zones === [] ? 'La livraison ne peut pas encore être calculée : aucune zone active n’est disponible.' : 'Validez votre livraison pour créer une commande et réserver les articles pendant 30 minutes. Aucun paiement n’est encaissé ici.' ?></p>
            </aside>
        </div>
    </div>
</section>
