<?php
declare(strict_types=1);
namespace App\Services;

use DomainException;

/** Frontière HTTP stricte ; jamais remplacée par un mode de test dans la requête. */
final class HttpImageUpload
{
    public static function source(mixed $file): string
    {
        if (!is_array($file)||!isset($file['error'])||!is_int($file['error'])) {
            throw new DomainException('Choisissez une photo JPEG, PNG ou WebP.',422);
        }
        if ($file['error']!==UPLOAD_ERR_OK) {
            $message=match($file['error']) {
                UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE=>'Cette photo dépasse la taille autorisée de 10 Mio.',
                UPLOAD_ERR_NO_FILE=>'Choisissez une photo à ajouter.',
                UPLOAD_ERR_PARTIAL=>'Le transfert de la photo est incomplet. Réessayez.',
                default=>'Le transfert de la photo est impossible pour le moment. Réessayez.',
            };
            throw new DomainException($message,422);
        }
        if (!isset($file['tmp_name'])||!is_string($file['tmp_name'])||!is_uploaded_file($file['tmp_name'])) {
            throw new DomainException('Le transfert de la photo est invalide. Réessayez.',422);
        }
        return $file['tmp_name'];
    }
}
