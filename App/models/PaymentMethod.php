<?php
declare(strict_types=1);
namespace App\Models;
use PDO;

final class PaymentMethod
{
    public const CASH = 'Espèces à la livraison';
    public function __construct(private readonly PDO $database) {}
    public function cash(bool $lock = false): ?array
    {
        $query = $this->database->prepare('SELECT * FROM mode_paiement WHERE nom = ?' . ($lock ? ' LOCK IN SHARE MODE' : ''));
        $query->execute([self::CASH]);
        return $query->fetch() ?: null;
    }
}
