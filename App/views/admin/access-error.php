<section class="container" style="padding-block:var(--spacing-xxl)">
    <h1><?= $accessStatus===403?'Accès réservé':($accessStatus===404?'Commande introuvable':'Administration indisponible') ?></h1>
    <p><?= $accessStatus===403?'Cet espace est réservé aux administrateurs actifs.':($accessStatus===404?'Cette référence ne correspond à aucune commande disponible.':'Réessayez dans quelques instants.') ?></p>
    <a class="btn btn-primary" href="/boutique">Retour à la boutique</a>
    <a class="btn btn-ghost" href="/compte">Mon compte</a>
</section>
