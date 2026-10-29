<?php
declare(strict_types=1);
namespace App\Models;
use PDO;

/** Requêtes globales Admin, sans verrou ni écriture métier. */
final class AdminOrderRead
{
    public function __construct(private readonly PDO $database) {}

    public function listing(string $state, bool $cash, int $offset): array
    {
        $where = []; $values = [];
        if ($state !== '') { $where[] = 'c.statut = ?'; $values[] = $state; }
        if ($cash) $where[] = "EXISTS (SELECT 1 FROM paiement p WHERE p.id_commande=c.id_commande AND p.statut='a_encaisser')";
        $q = $this->database->prepare($this->select() . ($where ? ' WHERE '.implode(' AND ', $where) : '') . ' ORDER BY c.date_commande DESC,c.id_commande DESC LIMIT 21 OFFSET ?');
        foreach ($values as $i => $value) $q->bindValue($i+1,$value);
        $q->bindValue(count($values)+1,$offset,PDO::PARAM_INT); $q->execute();
        return $q->fetchAll();
    }

    public function byReference(string $reference): ?array
    {
        $q = $this->database->prepare($this->select().' WHERE c.numero_commande=?');
        $q->execute([$reference]); return $q->fetch() ?: null;
    }

    /** Deux lectures pour toute la page, quel que soit le nombre de cartes. */
    public function relatedForPage(array $ids): array
    {
        $result=['payments'=>[],'deliveries'=>[]];
        if ($ids===[]) return $result;
        $marks=implode(',',array_fill(0,count($ids),'?'));
        $q=$this->database->prepare('SELECT p.*,m.nom AS mode FROM paiement p JOIN mode_paiement m USING(id_mode_paiement) WHERE p.id_commande IN ('.$marks.') ORDER BY p.id_paiement');
        $q->execute(array_values($ids));
        foreach($q->fetchAll() as $row) $result['payments'][(int)$row['id_commande']][]=$row;
        $q=$this->database->prepare('SELECT * FROM livraison WHERE id_commande IN ('.$marks.')');
        $q->execute(array_values($ids));
        foreach($q->fetchAll() as $row) $result['deliveries'][(int)$row['id_commande']]=$row;
        return $result;
    }

    private function select(): string
    {
        // Pas de jointure paiement ici : plusieurs tentatives ne dupliquent pas les commandes.
        return 'SELECT c.*,u.nom AS client_nom,u.prenom AS client_prenom,u.email AS client_email,u.telephone AS client_telephone FROM commande c LEFT JOIN utilisateur u ON u.id_utilisateur=c.id_utilisateur';
    }
}
