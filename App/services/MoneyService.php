<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use InvalidArgumentException;
use RuntimeException;

/** Les calculs utilisent des entiers : cents USD et francs CDF entiers. */
final class MoneyService
{
    public const REFERENCE = 'USD';
    public const ALTERNATIVE = 'CDF';
    private ?array $requestConfiguration = null;

    public function __construct(private readonly ?Setting $settings = null) {}

    public function configuration(): array
    {
        if ($this->requestConfiguration !== null) return $this->requestConfiguration;
        $json = $this->settings?->moneyConfiguration();
        $config = $json === null ? null : json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($config) || ($config['reference'] ?? '') !== self::REFERENCE || ($config['alternative'] ?? '') !== self::ALTERNATIVE) {
            throw new RuntimeException('Configuration monétaire indisponible.');
        }
        $this->rateUnits((string) ($config['usd_cdf_rate'] ?? ''));
        return $this->requestConfiguration = $config;
    }

    /** Point d'entree du futur Admin ; aucune route publique ne l'expose. */
    public function updateRate(string $rate): void
    {
        $this->rateUnits($rate);
        $config = $this->configuration();
        $config['usd_cdf_rate'] = $rate;
        $this->settings->updateMoneyConfiguration(json_encode($config, JSON_THROW_ON_ERROR));
        $this->requestConfiguration = $config;
    }

    public function minor(string $amount): int
    {
        if (!preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/D', $amount, $match)) {
            throw new InvalidArgumentException('Montant invalide.');
        }
        return (int) $match[1] * 100 + (int) str_pad($match[2] ?? '', 2, '0');
    }

    public function decimal(int $minor): string
    {
        if ($minor < 0) throw new InvalidArgumentException('Montant négatif.');
        return intdiv($minor, 100) . '.' . str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }

    public function format(int $minor, ?string $currency = self::REFERENCE): string
    {
        if ($minor < 0) throw new InvalidArgumentException('Montant négatif.');
        if ($currency === self::REFERENCE) return '$' . $this->decimal($minor);
        if ($currency === self::ALTERNATIVE) return $this->group($minor) . ' FC';
        if ($currency !== null) throw new InvalidArgumentException('Devise non prise en charge.');
        return $this->group(intdiv($minor, 100)) . ',' . str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT) . ' (devise DEV non définie)';
    }

    public function usdToCdf(int $usdCents): int
    {
        if ($usdCents < 0 || $usdCents > 999999999999) throw new InvalidArgumentException('Montant hors limites.');
        $rate = $this->rateUnits((string) $this->configuration()['usd_cdf_rate']);
        // Decomposer avant multiplication evite de depasser les entiers 64 bits.
        return intdiv($usdCents, 1000000) * $rate + $this->roundDivide(($usdCents % 1000000) * $rate, 1000000);
    }

    public function cdfToUsd(int $cdfFrancs): int
    {
        if ($cdfFrancs < 0 || $cdfFrancs > 999999999999) throw new InvalidArgumentException('Montant hors limites.');
        $rate = $this->rateUnits((string) $this->configuration()['usd_cdf_rate']);
        return intdiv($cdfFrancs, $rate) * 1000000 + $this->roundDivide(($cdfFrancs % $rate) * 1000000, $rate);
    }

    public function discounted(int $baseMinor, string $percent): int
    {
        $basisPoints = $this->minor($percent);
        if ($basisPoints > 10000) throw new InvalidArgumentException('Remise invalide.');
        return $this->roundDivide($baseMinor * (10000 - $basisPoints), 10000);
    }

    private function rateUnits(string $rate): int
    {
        if (!preg_match('/^(\d{1,6})(?:\.(\d{1,4}))?$/D', $rate, $match)) throw new InvalidArgumentException('Taux invalide.');
        $units = (int) $match[1] * 10000 + (int) str_pad($match[2] ?? '', 4, '0');
        if ($units < 1 || $units > 1000000000) throw new InvalidArgumentException('Le taux doit être compris entre 0.0001 et 100000.');
        return $units;
    }

    private function roundDivide(int $numerator, int $denominator): int
    {
        return intdiv($numerator, $denominator) + (($numerator % $denominator) * 2 >= $denominator ? 1 : 0);
    }

    private function group(int $value): string
    {
        return preg_replace('/\B(?=(\d{3})+(?!\d))/', ' ', (string) $value);
    }
}
