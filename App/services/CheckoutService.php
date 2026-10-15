<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DeliveryZone;
use App\Models\User;
use App\Models\UserAddress;

/** Prepare une livraison, sans creer de commande ou de paiement. */
final class CheckoutService
{
    private readonly MoneyService $money;
    public function __construct(
        private readonly CartService $cartService,
        private readonly AuthService $authService,
        private readonly ?User $users,
        private readonly ?UserAddress $addresses,
        private readonly ?DeliveryZone $zones,
        ?MoneyService $money = null,
    ) { $this->money = $money ?? new MoneyService(); }

    public function context(int $userId): array
    {
        if ($userId < 1) {
            return ['error' => 'Connectez-vous pour continuer.', 'status' => 401];
        }
        if ($this->users === null || $this->addresses === null || $this->zones === null) {
            return ['error' => 'La livraison est momentanément indisponible.', 'status' => 503];
        }
        $profile = $this->users->findCheckoutProfile($userId);
        if ($profile === null) {
            return ['error' => 'Votre compte est indisponible. Reconnectez-vous.', 'status' => 401];
        }
        $cart = $this->cartService->buildConnectedCart($userId);
        if (!$cart['canCheckout']) {
            return ['error' => $cart['items'] === [] ? 'Votre panier est vide. Ajoutez un produit pour continuer.' : 'Retirez les articles indisponibles de votre panier pour continuer.', 'status' => 422];
        }
        return ['profile' => $profile, 'cart' => $cart, 'addresses' => $this->addresses->forUser($userId), 'zones' => $this->zones->active()];
    }

    /** Ignore tout montant fourni par le navigateur ; relit panier et tarif. */
    public function summary(int $userId, int $zoneId): array
    {
        $context = $this->context($userId);
        if (isset($context['error'])) {
            return $context;
        }
        $zone = $this->zones->findActive($zoneId);
        if ($zone === null) {
            return ['error' => 'Choisissez une zone de livraison disponible.', 'status' => 422];
        }
        if ($context['cart']['currencyReference'] !== MoneyService::REFERENCE || ($zone['devise_reference'] ?? null) !== MoneyService::REFERENCE) {
            return ['error' => 'La devise de ces données DEV doit être définie avant tout calcul commercial.', 'status' => 422];
        }
        $subtotal = (int) $context['cart']['subtotalMinor'];
        $shipping = $this->money->minor((string) $zone['frais_reference']);
        $config = $this->money->configuration();
        return ['subtotal' => $this->money->format($subtotal), 'shipping' => $this->money->format($shipping), 'total' => $this->money->format($subtotal + $shipping), 'totalCdf' => $this->money->format($this->money->usdToCdf($subtotal + $shipping), MoneyService::ALTERNATIVE), 'monetarySnapshot' => ['reference' => MoneyService::REFERENCE, 'alternative' => MoneyService::ALTERNATIVE, 'usd_cdf_rate' => $config['usd_cdf_rate']], 'zone' => $zone, 'cart' => $context['cart']];
    }

    public function validateDelivery(int $userId, array $input): array
    {
        $context = $this->context($userId);
        if (isset($context['error'])) {
            return $context;
        }
        $addressId = filter_var($input['address_id'] ?? 0, FILTER_VALIDATE_INT);
        if ($addressId === false || $addressId < 0) {
            return ['error' => 'Sélectionnez une adresse valide.', 'status' => 422];
        }
        $saved = $addressId ? $this->addresses->findForUser($userId, (int) $addressId) : null;
        if ($addressId && $saved === null) {
            return ['error' => 'Cette adresse ne vous appartient pas ou n’existe plus.', 'status' => 422];
        }
        $source = $saved ?? $input;
        ['address' => $address, 'errors' => $errors] = $this->normalizeAddress($source);
        $zoneId = filter_var($source['id_zone_livraison'] ?? null, FILTER_VALIDATE_INT);
        $summary = $this->summary($userId, $zoneId !== false ? (int) $zoneId : 0);
        if (isset($summary['error'])) {
            $errors['id_zone_livraison'] = $summary['error'];
        }
        if ($errors !== []) {
            return ['error' => 'Vérifiez les informations de livraison.', 'errors' => $errors, 'status' => 422];
        }
        $address['id_zone_livraison'] = (int) $zoneId;
        $save = !$saved && ($input['save_address'] ?? '') === '1';
        if ($save) {
            // La premiere adresse peut devenir le choix par defaut ; les suivantes restent explicites.
            $default = $context['addresses'] === [] || ($input['make_default'] ?? '') === '1';
            $addressId = $this->addresses->createForUser($userId, $address, $default);
        }
        return ['success' => true, 'address' => $address, 'addressId' => (int) $addressId, 'summary' => $summary, 'saved' => $save];
    }

    /** Validation commune à la livraison et à sa relecture avant commande. */
    public function normalizeAddress(array $source): array
    {
        $errors = [];
        $address = [];
        foreach (['nom_destinataire' => 100, 'prenom_destinataire' => 100, 'commune' => 100, 'adresse' => 255, 'repere' => 255, 'instructions' => 1000] as $field => $max) {
            $value = is_string($source[$field] ?? null) ? trim($source[$field]) : '';
            $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
            if ($length > $max || ($value === '' && !in_array($field, ['repere', 'instructions'], true))) $errors[$field] = 'Renseignez ce champ (maximum ' . $max . ' caractères).';
            $address[$field] = $value !== '' ? $value : null;
        }
        $address['telephone'] = $this->authService->normalizeRdcPhone(is_string($source['telephone'] ?? null) ? $source['telephone'] : '');
        if ($address['telephone'] === null) $errors['telephone'] = 'Saisissez un numéro RDC valide (+243…, 243… ou 0…).';
        return ['address' => $address, 'errors' => $errors];
    }

}
