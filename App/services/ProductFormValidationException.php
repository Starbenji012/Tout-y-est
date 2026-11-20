<?php
declare(strict_types=1);
namespace App\Services;

/** Transporte l'erreur serveur jusqu'au champ, sans interpréter le texte du message. */
final class ProductFormValidationException extends \DomainException
{
    public readonly array $errors;

    public function __construct(string|array $field,?string $message=null)
    {
        $this->errors=is_array($field)?$field:[$field=>$message];
        parent::__construct((string)(array_values($this->errors)[0]??'Vérifiez les champs signalés.'),422);
    }
}
