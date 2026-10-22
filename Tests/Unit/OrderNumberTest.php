<?php
declare(strict_types=1);

// Toutes les allocations et commandes de ce test restent dans une base jetable.
$root = dirname(__DIR__, 2);
require_once $root.'/App/models/Order.php';
$config = require $root.'/Config/database.php';
if (!in_array($config['host'], ['localhost','127.0.0.1','::1'], true)) throw new RuntimeException('Serveur local requis.');
$connect = static fn (string $name): PDO => new PDO('mysql:host='.$config['host'].';port='.$config['port'].';dbname='.$name.';charset=utf8mb4', $config['username'], $config['password'], $config['options']);
$data = static fn (): array => ['user_id'=>1,'subtotal'=>'20','shipping'=>'5','total'=>'25','rate'=>'2450','key_hash'=>hash('sha256',random_bytes(32))];
if (($argv[1] ?? '') === 'worker') {
    $name = $argv[2] ?? '';
    if (!preg_match('/^tout_y_est_numbers_test_[a-f0-9]{16}$/D', $name)) throw new RuntimeException('Base refusée.');
    $db = $connect($name);
    while (microtime(true) < (float)$argv[3]) usleep(1000);
    $model = new App\Models\Order($db);
    $db->beginTransaction();
    $id = $model->createWithNumber($data());
    $db->commit();
    echo json_encode(['id'=>$id, 'number'=>$model->find($id)['numero_commande']]);
    exit;
}
$db = $connect($config['database']);
$name = 'tout_y_est_numbers_test_'.bin2hex(random_bytes(8));
$assert = static function (bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); };
try {
    $db->exec(str_replace('tout_y_est', $name, file_get_contents($root.'/Database/schema.sql')));
    // Simule aussi une ancienne base sans table d'allocation, dans la base jetable seulement.
    $db->exec('DROP TABLE numero_commande_sequence');
    $migration=file_get_contents($root.'/Database/migrations/S1.10_order_numbers.sql');
    $db->exec($migration);$db->exec($migration);
    $db->exec("INSERT INTO utilisateur(id_utilisateur,nom,prenom,email,mot_de_passe) VALUES(1,'TEST','TEST','numbers@example.invalid','unused')");
    $model = new App\Models\Order($db);
    $create = static function () use ($db, $model, $data): array {
        $db->beginTransaction();
        $id = $model->createWithNumber($data());
        $db->commit();
        return $model->find($id);
    };
    $assert(App\Models\Order::formatNumber('1')==='TYE-001' && App\Models\Order::formatNumber('42')==='TYE-042', 'Padding minimal');
    $first = $create();$second = $create();
    $assert($first['numero_commande']==='TYE-001' && $second['numero_commande']==='TYE-002', 'Départ progressif');
    $db->exec("UPDATE commande SET statut='annulee' WHERE id_commande=".(int)$first['id_commande']);
    $db->exec('DELETE FROM commande WHERE id_commande='.(int)$second['id_commande']);
    $assert($create()['numero_commande']==='TYE-003', 'Ni suppression ni annulation ne recyclent un numéro');
    $db->beginTransaction();$rolledBack=$model->createWithNumber($data());$number=$model->find($rolledBack)['numero_commande'];$db->rollBack();
    $assert($number==='TYE-004' && $create()['numero_commande']==='TYE-005', 'Rollback : trou accepté, jamais de recyclage');
    $old=$model->create($data()+['number'=>'TYE-20261019-A713584AB8AC8BE0']);
    $model->create($data()+['number'=>'TYE-042']);
    $db->exec($migration);$db->exec($migration);
    $assert($create()['numero_commande']==='TYE-043', 'Migration rejouable, reprise des numéros numériques existants');
    $assert($model->find($old)['numero_commande']==='TYE-20261019-A713584AB8AC8BE0', 'Historique inchangé');
    $db->exec('ALTER TABLE numero_commande_sequence AUTO_INCREMENT=999');
    $assert($create()['numero_commande']==='TYE-999' && $create()['numero_commande']==='TYE-1000', 'Passage 999 vers 1000 sans troncature');
    $workers=[];$start=microtime(true)+1;
    for($i=0;$i<2;$i++) {
        $process=proc_open([PHP_BINARY,__FILE__,'worker',$name,(string)$start],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        fclose($pipes[0]);$workers[]=[$process,$pipes];
    }
    $numbers=[];
    foreach($workers as [$process,$pipes]) {
        $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        $assert(proc_close($process)===0,'Worker : '.$err);
        $numbers[]=json_decode($out,true,512,JSON_THROW_ON_ERROR)['number'];
    }
    sort($numbers);
    $assert($numbers===['TYE-1001','TYE-1002'], 'Deux créations concurrentes, deux références distinctes');
    try {$model->create($data()+['number'=>'TYE-001']);throw new LogicException('Doublon accepté');}
    catch(PDOException $error) {$assert((int)$error->errorInfo[1]===1062,'Contrainte UNIQUE conservée');}
    echo "ORDER_NUMBERS_OK (padding, progression, 999/1000, concurrence, UNIQUE, rollback, suppression, migration rejouable, historique).\n";
} finally {
    if($db->inTransaction())$db->rollBack();
    if(!preg_match('/^tout_y_est_numbers_test_[a-f0-9]{16}$/D',$name))throw new RuntimeException('Nettoyage refusé');
    $db->exec('DROP DATABASE IF EXISTS `'.$name.'`');
}
