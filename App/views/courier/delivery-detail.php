<?php
$parcel=$activeParcel;$attempt=(int)$parcel['id_tentative'];
$currency=$parcel['devise_paiement']??$parcel['devise_reglement']??'USD';$amount=$currency==='USD'?$parcel['total']:$parcel['total_paiement'];
$reference=$parcel['numero_commande'];
?>
<a class="btn btn-outline" href="/livreur/livraisons">← Revenir à ma tournée</a>
<article class="courier-tour courier-delivery-focus" data-active-delivery="<?= $attempt ?>">
 <header><h2><?= $escape($reference) ?></h2><p class="courier-parcel__recipient"><?= $escape(trim($parcel['prenom_destinataire_snapshot'].' '.$parcel['nom_destinataire_snapshot'])) ?></p></header>
 <p class="courier-parcel__phone">Téléphone : <?php $phone=(string)$parcel['telephone_snapshot'];$dial=preg_replace('/[\s().-]/','',$phone);if(preg_match('/^\+?\d{7,15}$/D',$dial)): ?><a href="tel:<?= $escape($dial) ?>">Appeler <?= $escape($phone) ?></a><?php else: ?><?= $escape($phone) ?><?php endif; ?></p>
 <dl class="courier-delivery-address">
  <?php foreach(['adresse_snapshot'=>'Adresse','commune_snapshot'=>'Commune','nom_zone_snapshot'=>'Zone','repere_snapshot'=>'Repère','instructions_snapshot'=>'Instructions'] as $field=>$label): if(!empty($parcel[$field])): ?><div><dt><?= $label ?></dt><dd><?= $escape($parcel[$field]) ?></dd></div><?php endif; endforeach; ?>
 </dl>
 <div class="courier-parcel__payment"><strong><?= $escape($amount===null?'Montant à vérifier':$money($amount,$currency)) ?></strong><span><?= $escape($paymentLabel($parcel)) ?></span></div>
 <?php if($activeTour['statut']==='en_cours'&&$parcel['resultat']==='en_attente'): ?>
  <form method="post" action="/livreur/livraison/resultat" data-tour-form data-tour-confirm="<?= $escape('Confirmer la livraison de '.$reference.' ET la réception de '.($amount===null?'la somme indiquée':$money($amount,$currency)).' en espèces ?') ?>">
   <?php $token(); ?><input type="hidden" name="tentative" value="<?= $attempt ?>"><input type="hidden" name="resultat" value="livree">
   <p>Pour <?= $escape($reference) ?> uniquement, après remise du colis et réception du paiement.</p><button class="btn btn-primary">Livraison réussie — paiement reçu</button>
  </form>
  <details class="courier-exception"><summary>Signaler un problème</summary><form method="post" action="/livreur/livraison/resultat" data-tour-form>
   <?php $token(); ?><input type="hidden" name="tentative" value="<?= $attempt ?>"><input type="hidden" name="resultat" value="probleme">
   <label>Motif<select name="motif" required><option value="">Choisir</option><?php foreach($reasons as $value=>$label): ?><option value="<?= $value ?>"><?= $label ?></option><?php endforeach; ?></select></label>
   <label>Commentaire (obligatoire pour « Autre »)<textarea name="commentaire" maxlength="500"></textarea></label><button class="btn btn-outline">Enregistrer le problème pour <?= $escape($reference) ?></button>
  </form></details>
 <?php else: ?><p class="courier-notice">Récupérez les colis et confirmez votre départ depuis la tournée avant de traiter une livraison.</p><?php endif; ?>
</article>
