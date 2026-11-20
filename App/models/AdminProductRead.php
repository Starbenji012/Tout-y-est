<?php
declare(strict_types=1);
namespace App\Models;

use PDO;

require_once __DIR__ . '/CatalogVisibility.php';

/** Lecture Admin indépendante du contrat public : brouillons et archives inclus. */
final class AdminProductRead
{
    public function __construct(private readonly PDO $database) {}

    public function page(array $filters, int $page, int $limit): array
    {
        $conditions=[]; $params=[];
        if ($filters['q']!=='') {
            $conditions[]='(p.nom LIKE :name OR EXISTS (SELECT 1 FROM variante_produit s WHERE s.id_produit=p.id_produit AND s.sku LIKE :sku))';
            $params['name']=$params['sku']='%'.$filters['q'].'%';
        }
        if ($filters['statut']!=='') {
            $conditions[]=$filters['statut']==='actif' ? "LOWER(p.statut) IN ('actif','active')" : 'LOWER(p.statut)=:status';
            if ($filters['statut']!=='actif') $params['status']=$filters['statut'];
        }
        if ($filters['categorie']!==null) {$conditions[]='p.id_categorie=:category';$params['category']=$filters['categorie'];}
        $where=$conditions ? ' WHERE '.implode(' AND ',$conditions) : '';
        $count=$this->database->prepare('SELECT COUNT(*) FROM produit p'.$where);$count->execute($params);
        $total=(int)$count->fetchColumn(); $pages=max(1,(int)ceil($total/$limit)); $page=min($page,$pages);
        $query=$this->database->prepare('SELECT p.id_produit,p.nom,p.statut,c.nom AS categorie FROM produit p JOIN categorie c ON c.id_categorie=p.id_categorie'.$where.' ORDER BY p.date_creation DESC,p.id_produit DESC LIMIT :limit OFFSET :offset');
        foreach ($params as $key=>$value) $query->bindValue(':'.$key,$value,is_int($value)?PDO::PARAM_INT:PDO::PARAM_STR);
        $query->bindValue(':limit',$limit,PDO::PARAM_INT);$query->bindValue(':offset',($page-1)*$limit,PDO::PARAM_INT);$query->execute();
        return ['rows'=>$query->fetchAll(),'total'=>$total,'page'=>$page,'pages'=>$pages];
    }

    public function categories(): array
    {
        return $this->database->query('SELECT id_categorie,nom FROM categorie ORDER BY nom,id_categorie')->fetchAll();
    }

    /** Sépare les variantes actives sans perdre le nombre total ni le stock inactif. */
    public function variants(array $ids): array
    {
        if ($ids===[]) return [];
        $active=CatalogVisibility::activeSql('statut');
        $query=$this->database->prepare('SELECT id_produit,devise_reference,CASE WHEN '.$active.' THEN 1 ELSE 0 END AS is_active,COUNT(*) AS quantity,SUM(stock) AS stock,MIN(prix_reference) AS minimum,MAX(prix_reference) AS maximum FROM variante_produit WHERE id_produit IN ('.implode(',',array_fill(0,count($ids),'?')).') GROUP BY id_produit,devise_reference,is_active ORDER BY id_produit,devise_reference,is_active');
        $query->execute($ids);return $query->fetchAll();
    }

    public function images(array $ids): array
    {
        if ($ids===[]) return [];
        $query=$this->database->prepare('SELECT id_produit,chemin FROM image_produit WHERE id_produit IN ('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY image_principale DESC,ordre_affichage,id_image');
        $query->execute($ids);return $query->fetchAll();
    }
}
