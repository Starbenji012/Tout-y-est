<?php
declare(strict_types=1);
namespace App\Models;
use PDO;
use DomainException;

/** Identité du livreur, distincte des comptes clients et des auteurs Admin. */
final class Courier
{
    public function __construct(private readonly PDO $database) {}

    /** Même validation pour l'enregistrement et pour l'affectation d'une identité. */
    public static function cleanName(string $name): string
    {
        $name=trim($name);
        if(!preg_match('//u',$name)||preg_match('/[\x00-\x1f\x7f]/u',$name))throw new DomainException('Indiquez un nom de livreur valide.',422);
        $name=preg_replace('/\s+/u',' ',$name);
        $length=function_exists('mb_strlen')?mb_strlen($name):preg_match_all('/./u',$name);
        if($length<2||$length>120)throw new DomainException('Le nom du livreur doit contenir entre 2 et 120 caractères.',422);
        return $name;
    }

    public function isReady(): bool
    {
        $query=$this->database->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='livreur' AND COLUMN_NAME IN ('id_livreur','nom','statut')");
        return (int)$query->fetchColumn()===3;
    }

    public function listing(): array
    {
        return $this->database->query('SELECT id_livreur,nom,statut FROM livreur ORDER BY nom,id_livreur')->fetchAll();
    }

    public function create(string $name): int
    {
        // Enregistré ne signifie pas encore disponible pour une affectation.
        $query=$this->database->prepare("INSERT INTO livreur(nom,statut) VALUES(?,'inactif')");
        $query->execute([$name]);return (int)$this->database->lastInsertId();
    }

    public function lock(int $id): ?array
    {
        $query=$this->database->prepare('SELECT id_livreur,nom,statut FROM livreur WHERE id_livreur=? FOR UPDATE');
        $query->execute([$id]);return $query->fetch()?:null;
    }

    public function rename(int $id,string $name): void
    {
        $query=$this->database->prepare('UPDATE livreur SET nom=? WHERE id_livreur=?');
        $query->execute([$name,$id]);
    }

    public function setAvailability(int $id,string $status): void
    {
        $query=$this->database->prepare('UPDATE livreur SET statut=? WHERE id_livreur=?');
        $query->execute([$status,$id]);
    }
    public function active(): array
    {
        return $this->database->query("SELECT id_livreur,nom FROM livreur WHERE statut='actif' ORDER BY nom,id_livreur")->fetchAll();
    }
    public function findForAssignment(int $id): ?array
    {
        $query=$this->database->prepare('SELECT id_livreur,nom,statut FROM livreur WHERE id_livreur=? LOCK IN SHARE MODE');
        $query->execute([$id]);return $query->fetch()?:null;
    }
}
