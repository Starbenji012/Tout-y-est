<?php
$escape=static fn(mixed $v):string=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$token=static function()use($escape,$csrfToken):void{ ?><input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>"><?php };
$money=static fn(mixed $value,string $currency):string=>number_format((float)$value,2,',',' ').' '.$currency;
$reasons=['absent'=>'Client absent','injoignable'=>'Client injoignable','adresse'=>'Adresse incorrecte','refus'=>'Refus','paiement'=>'Problème de paiement','autre'=>'Autre'];
$paymentLabel=static function(array $parcel):string{
    return match($parcel['paiement_statut']){
        'a_encaisser'=>'À recevoir',
        'collecte_livreur'=>'Paiement reçu',
        'paye'=>isset($parcel['montant_collecte'])?'Paiement reçu — remis au gérant':'Paiement confirmé',
        'annule'=>'Paiement annulé',default=>'Règlement à vérifier',
    };
};
$activeParcel=$activeParcel??null;
// La tournée actuelle passe avant l'historique, qui reste replié par défaut.
usort($tours,static fn(array $a,array $b):int=>(int)in_array($b['statut'],['chargement','en_cours'],true)<=>(int)in_array($a['statut'],['chargement','en_cours'],true)?:((int)$b['id_tournee']<=>(int)$a['id_tournee']));
?>
<section class="container courier-tours">
 <header class="courier-page-heading"><h1>Mes livraisons</h1><p><?= $escape($courier['nom']) ?></p></header>
 <?php if(is_string($flash)): ?><p class="courier-notice" role="status"><?= $escape($flash) ?></p><?php endif; ?>
 <?php if($activeParcel!==null): ?>
  <?php require __DIR__.'/delivery-detail.php'; ?>
 <?php else: ?>
  <?php if(!$tours): ?><p>Aucun colis affecté pour le moment.</p><?php endif; ?>
  <?php if($tours&&!array_filter($tours,static fn(array $tour):bool=>in_array($tour['statut'],['chargement','en_cours'],true))): ?><p>Aucun colis à traiter pour le moment. Vos anciennes livraisons restent disponibles ci-dessous.</p><?php endif; ?>
  <?php foreach($tours as $tour):
   $id=(int)$tour['id_tournee'];$current=in_array($tour['statut'],['chargement','en_cours'],true);
   $items=array_values(array_filter($parcels,static fn(array $p):bool=>(int)$p['id_tournee']===$id));
   $groups=['pending'=>[],'problem'=>[],'done'=>[]];
   foreach($items as $parcel)$groups[match($parcel['resultat']){'en_attente'=>'pending','probleme'=>'problem',default=>'done'}][]=$parcel;
   $count=count($items);$cancelled=count(array_filter($items,static fn(array $p):bool=>$p['resultat']==='annulee'));
   $parcelCount=$count-$cancelled;$done=$parcelCount-count($groups['pending']);$success=count(array_filter($items,static fn(array $p):bool=>$p['resultat']==='livree'));
  ?>
   <?php if(!$current): ?><details class="courier-history"><summary><?= $tour['statut']==='retour_termine'?'Livraison précédente':'Historique — Départ annulé' ?> · <?= $count ?> colis</summary><?php endif; ?>
   <article class="courier-tour" data-tour-state="<?= $escape($tour['statut']) ?>">
    <header><h2><?= match($tour['statut']){'chargement'=>$parcelCount.' colis à récupérer','en_cours'=>$done===$parcelCount?'Tournée terminée':'Votre tournée','retour_termine'=>'Retour confirmé',default=>'Départ annulé'} ?></h2><p><?= $done ?> / <?= $parcelCount ?> colis traités</p></header>
    <?php if($tour['statut']==='chargement'): ?>
     <p>Vérifiez que vous avez bien récupéré les <?= $parcelCount ?> colis avant de partir. Aucun colis ne sera ajouté après votre départ.</p>
     <form method="post" action="/livreur/tournee/demarrer" data-tour-form><?php $token(); ?><input type="hidden" name="tournee" value="<?= $id ?>"><input type="hidden" name="version" value="<?= (int)$tour['version_manifeste'] ?>"><button class="btn btn-primary"<?= $courier['statut']!=='actif'?' disabled':'' ?>>J’ai récupéré <?= $parcelCount===1?'mon colis':'mes '.$parcelCount.' colis' ?></button></form>
    <?php endif; ?>
    <?php if($tour['statut']==='en_cours'&&$done===$parcelCount): ?>
     <div class="courier-completion" role="region" aria-label="Fin de tournée">
      <p><?= $success ?> <?= $success===1?'livraison réussie':'livraisons réussies' ?></p><p><?= count($groups['problem']) ?> colis à rapporter</p>
      <?php foreach($collectedTotals[$id]??[] as $currency=>$amount): ?><p><strong><?= $escape($money($amount,$currency)) ?> collectés</strong></p><?php endforeach; ?>
      <form class="courier-return" method="post" action="/livreur/tournee/retour" data-tour-form data-tour-confirm="Êtes-vous physiquement de retour avec les colis non livrés et le cash collecté ?"><?php $token(); ?><input type="hidden" name="tournee" value="<?= $id ?>"><p>Confirmez lorsque vous êtes revenu au point de récupération. Vous restez en tournée jusque-là.</p><button class="btn btn-primary">Confirmer mon retour</button></form>
     </div>
    <?php endif; ?>
    <?php foreach($groups as $group=>$groupItems): ?>
     <section class="courier-group" data-delivery-group="<?= $group ?>" aria-labelledby="group-<?= $id ?>-<?= $group ?>">
      <h3 id="group-<?= $id ?>-<?= $group ?>"><?= match($group){'pending'=>'À livrer','problem'=>'À rapporter',default=>'Terminées'} ?> <span><?= count($groupItems) ?></span></h3>
      <?php if(!$groupItems): ?><p class="courier-group__empty"><?= match($group){'pending'=>'Aucun colis à traiter.','problem'=>$current?'Aucun colis à rapporter.':'Aucun incident.',default=>'Aucune livraison terminée pour le moment.'} ?></p><?php endif; ?>
      <?php foreach($groupItems as $parcel): require __DIR__.'/delivery-card.php'; endforeach; ?>
     </section>
    <?php endforeach; ?>
   </article>
   <?php if(!$current): ?></details><?php endif; ?>
  <?php endforeach; ?>
 <?php endif; ?>
</section>
