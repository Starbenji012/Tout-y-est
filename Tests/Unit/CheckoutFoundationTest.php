<?php

declare(strict_types=1);

// Ce test utilise la BDD locale ; toutes ses insertions sont annulees.
$config = require dirname(__DIR__, 2) . '/Config/database.php';
$database = new PDO(
    "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}",
    $config['username'],
    $config['password'],
    $config['options'],
);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$reject = static function (string $sql) use ($database): void {
    try {
        $database->exec($sql);
    } catch (PDOException $exception) {
        if (in_array((int) ($exception->errorInfo[1] ?? 0), [1062, 1451, 1452, 4025, 3819], true)) {
            return;
        }
        throw $exception;
    }
    throw new RuntimeException('Une contrainte attendue n a pas bloque la requete.');
};
$tables = ['adresse_utilisateur', 'zone_livraison', 'commande', 'ligne_commande', 'livraison', 'mode_paiement', 'paiement'];
$before = [];
$schema = file_get_contents(dirname(__DIR__, 2) . '/Database/schema.sql');
$columnsQuery = $database->prepare('SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION');
foreach ($tables as $table) {
    $before[$table] = (int) $database->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
    $create = $database->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1];
    $assert(str_contains($create, 'ENGINE=InnoDB'), "$table doit utiliser InnoDB.");
    $assert(str_contains($create, 'AUTO_INCREMENT'), "$table doit generer ses identifiants.");
    $assert(str_contains($create, 'PRIMARY KEY'), "$table doit posseder une cle primaire.");
    // Comparer les colonnes reelles au schema de reference, sans le rejouer.
    preg_match('/CREATE TABLE ' . $table . ' \((.*?)\) ENGINE=InnoDB;/s', $schema, $definition);
    preg_match_all('/^    (\w+) ((?:BIGINT|INT)(?: UNSIGNED)?|BOOLEAN|VARCHAR\(\d+\)|DECIMAL\(\d+,\s*\d+\)|DATETIME|TEXT)([^\n]*)/m', $definition[1] ?? '', $expected, PREG_SET_ORDER);
    $columnsQuery->execute([$table]);
    $actual = $columnsQuery->fetchAll();
    $assert(count($actual) === count($expected) && count($expected) > 0, "$table : colonnes divergentes.");
    foreach ($expected as $position => $column) {
        $type = strtolower(str_replace(' ', '', $column[2]));
        $type = $type === 'boolean' ? 'tinyint' : $type;
        $actualType = preg_replace('/(bigint|int|tinyint)\(\d+\)/', '$1', $actual[$position]['COLUMN_TYPE']);
        $actualType = str_replace(' ', '', $actualType);
        $assert($column[1] === $actual[$position]['COLUMN_NAME'] && $type === $actualType, "$table : nom/type divergent.");
        $nullable = str_contains($column[3], 'NOT NULL') ? 'NO' : 'YES';
        $assert($nullable === $actual[$position]['IS_NULLABLE'], "$table : nullabilite divergente.");
    }
}
$foreignKeys = $database->query("SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL
    AND TABLE_NAME IN ('adresse_utilisateur','commande','ligne_commande','livraison','paiement')")->fetchAll();
$assert(count($foreignKeys) === 10, 'Les dix FK checkout sont requises.');
$money = $database->query("SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND (
        (TABLE_NAME = 'commande' AND COLUMN_NAME IN ('sous_total','frais_livraison','total')) OR
        (TABLE_NAME = 'ligne_commande' AND COLUMN_NAME IN ('prix_unitaire_applique','remise_unitaire_appliquee','total_ligne')) OR
        (TABLE_NAME = 'zone_livraison' AND COLUMN_NAME = 'frais_reference') OR
        (TABLE_NAME = 'livraison' AND COLUMN_NAME = 'frais_snapshot') OR
        (TABLE_NAME = 'paiement' AND COLUMN_NAME = 'montant'))")->fetchAll();
$assert(count($money) === 9, 'Les neuf champs monetaires sont requis.');
foreach ($money as $column) {
    $assert($column['DATA_TYPE'] === 'decimal', 'Un montant doit rester en DECIMAL.');
}
$userId = (int) $database->query('SELECT id_utilisateur FROM utilisateur LIMIT 1')->fetchColumn();
$variant = $database->query('SELECT id_variante, id_produit FROM variante_produit LIMIT 1')->fetch();
$assert($userId > 0 && is_array($variant), 'Un utilisateur et une variante existants sont requis pour ce test local.');
$productId = (int) $variant['id_produit'];
$variantId = (int) $variant['id_variante'];
$database->beginTransaction();
try {
    $database->exec("INSERT INTO zone_livraison (nom, frais_reference) VALUES ('TEST S1.5 transaction', 3.50)");
    $zoneId = (int) $database->lastInsertId();
    $database->exec("INSERT INTO adresse_utilisateur (id_utilisateur,id_zone_livraison,nom_destinataire,prenom_destinataire,telephone,commune,adresse)
        VALUES ($userId,$zoneId,'TEST','TEST','TEST','TEST','Adresse initiale')");
    $addressId = (int) $database->lastInsertId();
    $database->exec("INSERT INTO commande (id_utilisateur,numero_commande,sous_total,frais_livraison,total)
        VALUES ($userId,'TEST-S15-TRANSACTION',20.00,3.50,23.50)");
    $orderId = (int) $database->lastInsertId();
    $reject("INSERT INTO commande (id_utilisateur,numero_commande,sous_total,frais_livraison,total)
        VALUES ($userId,'TEST-S15-TRANSACTION',20,3.5,23.5)");
    $reject("INSERT INTO commande (id_utilisateur,numero_commande,sous_total,frais_livraison,total)
        VALUES ($userId,'TEST-S15-BAD-TOTAL',20,3.5,1)");
    $database->exec("INSERT INTO ligne_commande (id_commande,id_produit,id_variante,nom_produit_snapshot,sku_snapshot,quantite,prix_unitaire_applique,total_ligne)
        VALUES ($orderId,$productId,$variantId,'Nom fige TEST','SKU-FIGE-TEST',2,10.00,20.00)");
    $reject("UPDATE ligne_commande SET quantite = 0 WHERE id_commande = $orderId");
    $reject("UPDATE ligne_commande SET id_variante = 0 WHERE id_commande = $orderId");
    $database->exec("INSERT INTO livraison (id_commande,id_zone_livraison,nom_destinataire_snapshot,prenom_destinataire_snapshot,telephone_snapshot,commune_snapshot,adresse_snapshot,nom_zone_snapshot,frais_snapshot)
        VALUES ($orderId,$zoneId,'TEST','TEST','TEST','TEST','Adresse initiale','Zone figee TEST',3.50)");
    $database->exec("UPDATE adresse_utilisateur SET adresse = 'Adresse modifiee' WHERE id_adresse = $addressId");
    $assert($database->query("SELECT adresse_snapshot FROM livraison WHERE id_commande = $orderId")->fetchColumn() === 'Adresse initiale', 'Le snapshot ne doit pas suivre l adresse editable.');
    $reject("UPDATE adresse_utilisateur SET latitude = 91 WHERE id_adresse = $addressId");
    $database->exec("DELETE FROM zone_livraison WHERE id_zone_livraison = $zoneId");
    $assert($database->query("SELECT nom_zone_snapshot FROM livraison WHERE id_commande = $orderId")->fetchColumn() === 'Zone figee TEST', 'Supprimer une zone ne doit pas effacer son libelle historique.');
    $database->exec("INSERT INTO mode_paiement (nom) VALUES ('TEST S1.5 transaction')");
    $modeId = (int) $database->lastInsertId();
    $database->exec("INSERT INTO paiement (id_commande,id_mode_paiement,montant,reference_externe)
        VALUES ($orderId,$modeId,23.50,'TEST-S15-REFERENCE'),($orderId,$modeId,23.50,NULL)");
    $assert((int) $database->query("SELECT COUNT(*) FROM paiement WHERE id_commande = $orderId")->fetchColumn() === 2, 'Plusieurs tentatives doivent etre possibles.');
    $reject("INSERT INTO paiement (id_commande,id_mode_paiement,montant,reference_externe)
        VALUES ($orderId,$modeId,23.5,'TEST-S15-REFERENCE')");
    $reject("DELETE FROM commande WHERE id_commande = $orderId");
} finally {
    $database->rollBack();
}
foreach ($before as $table => $count) {
    $assert((int) $database->query("SELECT COUNT(*) FROM `$table`")->fetchColumn() === $count, 'Aucune donnee de test ne doit persister.');
}
echo "CheckoutFoundationTest: OK (contraintes, snapshots, tentatives, rollback).\n";
