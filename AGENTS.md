## Git workflow

* Branche principale : `main`.
* Dépôt distant : `origin`.
* Avant chaque commit, analyser `git status`, les différences et les fichiers concernés.
* Ne jamais committer `.env`, mots de passe, tokens, clés API, identifiants ou autres secrets.
* Toujours respecter `.gitignore`.
* Ne jamais modifier `.gitignore` pour contourner une exclusion sans l'autorisation explicite de l'utilisateur.
* Lorsque l'utilisateur dit « fais le commit », analyser les modifications, sélectionner les fichiers appropriés, générer un message de commit court et descriptif, puis effectuer le commit.
* Utiliser lorsque pertinent les préfixes `feat:`, `fix:`, `refactor:`, `docs:` ou `style:`.
* Après le commit, vérifier qu'il a réussi et contrôler l'état Git.
* Ne jamais effectuer `git push` automatiquement.
* Toujours demander la confirmation explicite de l'utilisateur avant `git push` vers `origin/main`.
* Ne jamais utiliser `git push --force`, `git reset --hard` ou une autre commande Git destructive sans l'autorisation explicite de l'utilisateur.
* En cas de secret potentiel, anomalie ou fichier douteux, arrêter le processus et prévenir l'utilisateur avant le commit.
