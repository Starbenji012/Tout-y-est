<?php
$attempt=(int)$parcel['id_tentative'];
$currency=$parcel['devise_paiement']??$parcel['devise_reglement']??'USD';
$amount=$parcel['montant_collecte']??($currency==='USD'?$parcel['total']:$parcel['total_paiement']);
$currency=$parcel['devise_collecte']??$currency;
?>
<?php if($group==='done'): ?><details class="courier-stop" data-delivery="<?= $attempt ?>"><summary><strong><?= $escape($parcel['numero_commande']) ?></strong><span><?= $parcel['resultat']==='livree'?'Livraison réussie':'Annulée' ?></span><?php if(isset($parcel['montant_collecte'])): ?><span class="courier-stop__receipt"><?= $escape($money($amount,$currency)) ?> — <?= $escape($paymentLabel($parcel)) ?></span><?php endif; ?></summary>
<?php else: ?><article class="courier-stop" data-delivery="<?= $attempt ?>"><h4><?= $escape($parcel['numero_commande']) ?></h4><?php endif; ?>
 <p class="courier-parcel__recipient"><?= $escape(trim($parcel['prenom_destinataire_snapshot'].' '.$parcel['nom_destinataire_snapshot'])) ?></p>
 <p><?= $escape($parcel['nom_zone_snapshot']?:$parcel['commune_snapshot']) ?> · <?= $escape($parcel['adresse_snapshot']) ?></p>
 <p class="courier-stop__amount"><strong><?= $escape($amount===null?'Montant à vérifier':$money($amount,$currency)) ?></strong> — <?= $escape($paymentLabel($parcel)) ?></p>
 <?php if($group==='pending'): ?>
  <a class="btn btn-outline" href="/livreur/livraisons?livraison=<?= $attempt ?>" aria-label="Ouvrir la livraison <?= $escape($parcel['numero_commande']) ?>"><?= $tour['statut']==='en_cours'?'Ouvrir la livraison':'Voir le colis' ?> →</a>
 <?php elseif($group==='problem'): ?>
  <p class="courier-stop__problem"><strong>Livraison non effectuée</strong><br><?= $current?'À rapporter au point de récupération':'Colis rapporté au point de récupération' ?><br>Motif : <?= $escape($reasons[$parcel['motif']??'']??'Non renseigné') ?></p>
 <?php endif; ?>
 <?php if($group!=='pending'): ?>
  <?php if($group==='problem'): ?><details><summary>Adresse et détails</summary><?php endif; ?>
  <p><?= $escape($parcel['commune_snapshot']) ?></p><p>Téléphone : <?= $escape($parcel['telephone_snapshot']) ?></p>
  <?php foreach(['repere_snapshot'=>'Repère','instructions_snapshot'=>'Instructions','commentaire'=>'Commentaire'] as $field=>$label): if(!empty($parcel[$field])): ?><p><?= $label ?> : <?= $escape($parcel[$field]) ?></p><?php endif; endforeach; ?>
  <?php if($group==='problem'): ?></details><?php endif; ?>
 <?php endif; ?>
<?php if($group==='done'): ?></details><?php else: ?></article><?php endif; ?>
