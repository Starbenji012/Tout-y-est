<?php
declare(strict_types=1);
namespace App\Services;

use Throwable;

require_once __DIR__ . '/ProductFormValidationException.php';

/** Contrat de saisie commun à la création 3A et au moteur 4B. */
final class ProductVariantInput
{
    public function __construct(private readonly MoneyService $money) {}

    public function sku(string $value): string
    {
        $sku = strtoupper(trim($value));
        if ($sku !== '' && !preg_match('/^[A-Z0-9][A-Z0-9._-]{0,99}$/D', $sku)) {
            throw new ProductFormValidationException('sku', 'Utilisez uniquement des lettres, chiffres, points, tirets ou underscores, en commençant par une lettre ou un chiffre (100 caractères maximum).');
        }
        return $sku;
    }

    public function generatedSku(): string { return 'TYE-'.strtoupper(bin2hex(random_bytes(8))); }

    public function price(string $value, string $currency): string
    {
        $amount = str_replace([' ', "\u{00A0}", "\u{202F}", ','], ['', '', '', '.'], trim($value));
        if (!in_array($currency, ['USD','CDF'], true)) throw new ProductFormValidationException('devise', 'Choisissez USD ou CDF.');
        if ($currency === 'USD') {
            try { $minor = $this->money->minor($amount); }
            catch (\InvalidArgumentException) { throw new ProductFormValidationException('prix', 'Le prix doit être un nombre et ne peut pas être négatif (deux décimales maximum en dollars).'); }
        } else {
            if (!preg_match('/^\d{1,12}(?:\.0{1,2})?$/D', $amount)) throw new ProductFormValidationException('prix', 'Le prix ne peut pas être négatif. En francs congolais, saisissez un nombre entier de 12 chiffres maximum.');
            try { $minor = $this->money->cdfToUsd((int)$amount); }
            catch (Throwable) { throw new ProductFormValidationException('devise', 'Le taux de change est indisponible. Réessayez plus tard ou saisissez le prix en USD.'); }
        }
        if ($minor > 999999999999) throw new ProductFormValidationException('prix', 'Ce prix dépasse la limite autorisée.');
        return $this->money->decimal($minor);
    }
}
