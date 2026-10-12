<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/App/core/Database.php';
require_once dirname(__DIR__, 2) . '/App/models/Setting.php';
require_once dirname(__DIR__, 2) . '/App/services/MoneyService.php';
$db = App\Core\Database::connect(require dirname(__DIR__, 2) . '/Config/database.php');
$dev = $db->query("SELECT sku, prix_reference, devise_reference FROM variante_produit WHERE sku IN ('DEV-CART-BAG-001','DEV-CART-HEADSET-001') ORDER BY sku")->fetchAll();
if (count($dev) === 2 && ($dev[0]['prix_reference'] !== '45000.00' || $dev[1]['prix_reference'] !== '78500.00' || $dev[0]['devise_reference'] !== null || $dev[1]['devise_reference'] !== null)) throw new RuntimeException('Données DEV réinterprétées');
// Cette configuration temporaire ne modifie jamais le taux du commerçant.
$db->exec('CREATE TEMPORARY TABLE parametre (id_parametre BIGINT PRIMARY KEY, cle VARCHAR(100) UNIQUE, valeur TEXT)');
$db->exec('INSERT INTO parametre VALUES (1, \'monetary_config\', \'{"reference":"USD","alternative":"CDF","usd_cdf_rate":"2450"}\')');
$money = new App\Services\MoneyService(new App\Models\Setting($db));
$assert = static function (bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); };
$snapshot = $money->configuration();
$assert($snapshot['reference'] === 'USD' && $snapshot['alternative'] === 'CDF', 'Devises serveur');
foreach ([20 => 49000, 35 => 85750, 100 => 245000] as $usd => $cdf) {
    $assert($money->usdToCdf($usd * 100) === $cdf, 'Conversion USD');
    $assert($money->cdfToUsd($cdf) === $usd * 100, 'Conversion inverse');
}
$assert($money->format(2000) === '$20.00' && $money->format(49000, 'CDF') === '49 000 FC', 'Formats');
foreach (['0', '-1', 'abc', '100001', '0.00001'] as $rate) {
    try { $money->updateRate($rate); throw new RuntimeException('Taux invalide accepté'); }
    catch (InvalidArgumentException) {}
}
$money->updateRate('2500');
$assert($money->usdToCdf(2000) === 50000 && $snapshot['usd_cdf_rate'] === '2450', 'Snapshot indépendant');
$assert(str_contains($money->format($money->minor('45000.00'), null), 'devise DEV'), 'DEV non converti');
echo "MONEY_OK (entiers, devises, conversions, taux invalides, snapshot).\n";
