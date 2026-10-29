<?php
declare(strict_types=1);

// Réutiliser les fixtures Checkout permet de tester la liaison sans données persistantes.
ob_start();
require __DIR__ . '/CheckoutDeliveryTest.php';
ob_end_clean();
$database->exec('ALTER TABLE zone_livraison MODIFY id_zone_livraison BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ADD UNIQUE (nom)');
require_once $root . '/App/services/DeliveryZoneService.php';
require_once $root . '/App/controllers/Admin/DeliveryController.php';
require_once $root . '/App/services/AdminNavigation.php';
require_once $root . '/App/controllers/Admin/AccessController.php';
$database->exec("INSERT INTO utilisateur (id_utilisateur,nom,prenom,email,telephone,mot_de_passe,role,statut) VALUES (90003,'TEST','Admin','admin@example.invalid','+243812345680','unused','admin','actif')");
$adminService = new App\Services\DeliveryZoneService(new App\Models\User($database), $zones, new App\Services\MoneyService(new App\Models\Setting($database)));
$assert($adminService->access(0)['status'] === 401, 'Session requise');
$assert($adminService->access(90001)['status'] === 403, 'Client refusé');
$assert($adminService->access(99999)['status'] === 403, 'Compte inexistant refusé');
$assert($adminService->access(90003)['success'], 'Admin autorisé');
$adminController = new App\Controllers\Admin\DeliveryController($adminService, new App\Core\Request());
$call = static function (int $actor, array $data, string $method = 'POST') use ($adminController): array {
    App\Core\Session::set('user', ['id' => $actor, 'role' => 'admin']);
    $_SERVER['REQUEST_METHOD'] = $method;
    $_POST = $data;
    ob_start(); $adminController->save();
    return json_decode((string) ob_get_clean(), true, 512, JSON_THROW_ON_ERROR);
};
$valid = ['nom' => 'TEST TEMPORAIRE Zone', 'frais_reference' => '3,25', 'statut' => 'actif', '_token' => App\Middleware\CsrfMiddleware::token()];
$call(90001, $valid + ['admin_id' => 90003, 'user_id' => 90003]);
$assert(http_response_code() === 403 && $zones->all() === [], 'Ni rôle session ni identité client ne suffisent');
$call(0, $valid); $assert(http_response_code() === 401, 'Invité refusé');
$call(90003, array_replace($valid, ['_token' => 'bad'])); $assert(http_response_code() === 419 && $zones->all() === [], 'CSRF requis');
$call(90003, $valid, 'GET'); $assert(http_response_code() === 405, 'POST requis');
$created = $call(90003, $valid + ['devise_reference' => 'CDF', 'usd_cdf_rate' => '1']);
$assert(http_response_code() === 200 && $created['success'], 'Création Admin');
$id = $created['id'];
$row = $zones->find($id);
$assert($row['frais_reference'] === '3.25' && $row['devise_reference'] === 'USD', 'Montant normalisé et devise serveur');
$assert(count($zones->active()) === 1 && $service->summary(90001, $id)['shipping'] === '$3.25', 'Checkout voit la zone active depuis BDD');
$edited = $call(90003, array_replace($valid, ['id_zone_livraison' => (string) $id, 'nom' => 'TEST TEMPORAIRE Zone modifiée', 'frais_reference' => '4.50']));
$assert($edited['success'] && $service->summary(90001, $id)['total'] === '$25.50', 'Modification et recalcul Checkout');
$call(90003, array_replace($valid, ['id_zone_livraison' => (string) $id, 'frais_reference' => '4.50', 'statut' => 'inactif']));
$assert($zones->active() === [] && $service->summary(90001, $id)['status'] === 422 && $zones->find($id) !== null, 'Désactivation sans suppression');
$call(90003, array_replace($valid, ['id_zone_livraison' => (string) $id]));
$assert(count($zones->active()) === 1, 'Réactivation');
foreach (['-1', 'abc', '1.001', '10000000000', '1e2', ['3.25']] as $fees) {
    $response = $call(90003, array_replace($valid, ['nom' => 'TEST montant invalide', 'frais_reference' => $fees]));
    $assert(http_response_code() === 422 && isset($response['errors']['frais_reference']), 'Frais invalides refusés');
}
$call(90003, $valid); $assert(http_response_code() === 422, 'Doublon refusé');
$call(90003, array_replace($valid, ['id_zone_livraison' => '99999'])); $assert(http_response_code() === 404, 'Zone absente refusée');
$call(90003, array_replace($valid, ['nom' => '', 'statut' => 'unknown'])); $assert(http_response_code() === 422, 'Nom et statut validés');
$database->exec('UPDATE zone_livraison SET devise_reference = NULL');
$call(90003, array_replace($valid, ['id_zone_livraison' => (string) $id])); $assert(http_response_code() === 422, 'Devise historique non réinterprétée');
$database->exec("UPDATE zone_livraison SET devise_reference = 'USD'");
$_SERVER['REQUEST_METHOD'] = 'GET';
App\Core\Session::set('user', ['id' => 90003]);
ob_start(); $adminController->index(); $html = (string) ob_get_clean();
$assert(str_contains($html, 'data-zone-form') && str_contains($html, 'Frais de livraison (USD)'), 'Interface réutilise le layout');
$assert(str_contains($html, 'admin-header') && !str_contains($html, 'data-header-search') && !str_contains($html, 'data-cart-count'), 'Layout Admin sans actions client');
$assert(str_contains($html, 'data-zone-create-toggle') && str_contains($html, 'data-zone-accordion'), 'Création repliable et accordéons natifs');
$assert(str_contains($html, 'data-zone-create hidden') && !str_contains($html, '<summary><span>Nouvelle zone</span></summary>') && str_contains($html, 'data-zone-create-close'), 'Un seul déclencheur de création, formulaire masqué et fermeture');
$database->exec("UPDATE utilisateur SET role = 'client' WHERE id_utilisateur = 90003");
ob_start(); $adminController->index(); ob_end_clean();
$assert(http_response_code() === 403, 'Révocation immédiate malgré la session');
$database->exec("UPDATE utilisateur SET role = 'admin', statut = 'inactif' WHERE id_utilisateur = 90003");
$assert($adminService->access(90003)['status'] === 403, 'Admin désactivé refusé');
echo "ADMIN_DELIVERY_ZONES_OK (tables temporaires, droits BDD, CRUD, CSRF, USD, Checkout).\n";
