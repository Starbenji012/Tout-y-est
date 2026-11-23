# Photos Admin — Phase 3B-2

- Le contrôleur exige Admin actif relu en BDD, POST, CSRF et un véritable
  fichier PHP `is_uploaded_file()`. Le nom/MIME navigateur n'est jamais utilisé.
- `UploadService` reste le moteur local validé de 3B-1, sans dépendance HTTP.
- `AdminProductImageService` verrouille le produit avant comptage, traitement et
  insertion. Toutes les écritures photo doivent respecter ce même verrou :
  deux ajouts concurrents ne peuvent pas dépasser six photos ni choisir tous
  les deux une principale. Le traitement garde ce verrou pour cette V1 simple.
- Chaque ajout utilise 16 octets aléatoires : `<hex32>-thumb.webp`,
  `<hex32>-medium.webp`, `<hex32>-large.webp`. Même une petite source produit
  trois noms stables (copie des résultats dédupliqués du moteur), sans upscale.
- `image_produit.chemin` contient `uploads/products/<hex32>-large.webp`.
  La miniature Admin se déduit du suffixe ; les anciens chemins `assets/...`
  restent tels quels et les consommateurs existants lisent toujours un chemin.
- Fichiers réservés exclusivement (`x+b`), copie complète puis insertion SQL et
  commit. Toute exception avant commit annule SQL et nettoie nos seuls fichiers.
  Les temporaires du moteur sont nettoyés aussi après succès. Aucun original
  uploadé n'est copié sous Public. La première image est principale, ordre zéro ;
  les suivantes utilisent l'ordre maximal + 1 et ne sont pas principales.
- SQL et le filesystem ne partagent pas une transaction distribuée : un arrêt
  brutal du processus/machine entre copie et commit peut laisser des WebP
  orphelins. Un futur rapprochement contrôlé pourra les détecter ; aucune
  suppression automatique large n'est introduite ici. Les erreurs de nettoyage
  sont journalisées sans chemin source ni secret.
- Serveur PHP intégré : les fichiers finaux `.webp` sont statiques, pas du PHP.
  `.htaccess` désactive index/exécution et refuse les autres noms pour Apache
  (nécessite AllowOverride adapté). Nginx/IIS ou un hébergement différent doit
  configurer ce dossier en statique sans exécution de scripts. Ce fichier
  Apache ne prétend pas configurer ces autres serveurs.

## Gestion de galerie — Phase 3B-3

- Principale, déplacements et suppression reprennent le même verrou produit
  et la revalidation Admin actif dans la transaction. L'appartenance de la
  photo est vérifiée avant toute mutation ; les ordres sont normalisés 0..N.
- La contrainte UNIQUE reste active : retirer d'abord l'ancienne principale,
  puis désigner la nouvelle, sans fenêtre visible hors transaction.
- Une dernière photo de produit actif n'est jamais supprimée. Brouillon et
  archivé peuvent avoir une galerie vide ; une galerie restante sans principale
  est réparée en promouvant sa première photo selon l'ordre.
- Les fichiers ne sont supprimés qu'après commit SQL réussi. Un rollback SQL
  conserve ainsi tous les fichiers. Seuls les trois noms opaques strictement
  conformes et directement situés dans le dossier autorisé sont éligibles ;
  les liens symboliques et chemins uploads suspects sont refusés. Toute
  référence partagée conserve ses fichiers. Aucun fichier assets n'est effacé.
- Si le nettoyage après commit échoue : la relation est déjà retirée, les
  fichiers résiduels sont orphelins mais aucune ligne volontairement cassée
  n'est conservée. Message de maintenance et log explicites. Un arrêt brutal
  après commit peut également laisser tout ou partie de ces dérivés orphelins.
  Pas de transaction distribuée ni nettoyage large automatique pour cette V1.
- L'ordre des miniatures client est indépendant de la principale : celle-ci
  demeure l'image initialement affichée et la miniature sélectionnée.

Aucune migration ni publication automatique.
