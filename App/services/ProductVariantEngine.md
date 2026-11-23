# Moteur options / variantes — Phase 4B

Précondition de déploiement : S1.16. **Cette phase ne l'applique pas à la base
réelle** et ne branche ni route, ni écran, ni sélection publique. Le service
est appelé sur la connexion PDO de l'application et relit l'Admin actif depuis
la session/BDD. Le futur contrôleur 4C devra vérifier POST + CSRF comme les
autres contrôleurs Admin ; aucune identité Admin issue d'un formulaire.

## API et transition

`AdminProductVariantService(PDO, AdminAccessService, MoneyService)` :

- `saveVariants(produit, lignes, prixCommun?)` : lot explicitement choisi.
  Chaque ligne peut avoir `id_variante` (modification) ou l'omettre (création),
  `valeurs` sous forme `id_option => id_valeur`, `sku`, `prix`, `devise`,
  `stock`, `stock_attendu`, `statut` (`actif` / `inactif`). Les champs absents
  d'une modification conservent leur valeur. Une création exige prix et stock.
  Le prix commun `{prix, devise}` s'applique uniquement aux lignes du lot et
  exclut les prix individuels simultanés. Chaque prix reste sur sa variante.
- Sans options : clé `simple`, une seule variante technique (existante réutilisée
  explicitement par son ID). Aucun attribut « Standard » inventé. Une ancienne
  description libre est conservée. Plusieurs anciennes variantes NULL sans
  options exigent une décision explicite, pas une fusion automatique.
- `initialize(produit, definitions, lignes, prixCommun?)` : première définition
  d'options et conversion atomique. Exemple de définition :
  `[{nom: Couleur, valeurs: [Noir, Blanc]}, {nom: Taille, valeurs: [S, M]}]`.
  Chaque ligne porte `selection: {1: 1, 2: 2}` (positions **à partir de 1**,
  pas des IDs inventés). Le serveur crée les vrais IDs et traduit la sélection.
  Le résultat contient `options` (correspondance positions → IDs) et `variantes`
  (IDs dans l'ordre des lignes). Toutes les anciennes variantes, même inactives,
  doivent être explicitement associées via leur ID ; on ne devine jamais à
  quelle combinaison rattacher le simple existant. Les données commerciales
  omises sont préservées. L'ensemble est rollbacké si une combinaison manque.
- `addValue`, `renameOption`, `renameValue`, `removeValue`, `removeOption` :
  propriétaires vérifiés. Pas de suppression physique de variante exposée.
  Désactivation via `saveVariants`. Une valeur utilisée ne peut être supprimée.
  Une option ne peut être retirée tant que le produit a des variantes.

Pas d'ajout/retrait d'option sur un ensemble déjà classifié : cela changerait
l'identité de toutes les combinaisons. Une évolution de structure demandera
un lot explicite et une décision UX ultérieure, jamais une réaffectation
silencieuse. L'ajout de valeurs à une option existante est possible sans créer
de variantes cartésiennes. Les labels sont normalisés (espaces Unicode/bords,
espaces intérieurs), validés UTF-8, sans contrôles, 1..100 caractères ;
l'unicité insensible à la casse/aux accents est assurée par la collation 4A.

## Identité et historique

Clé calculée exclusivement par le serveur : IDs positifs, exactement une valeur
par option du produit, tri **numérique** par ID d'option. Les FK composites et
UNIQUE restent la deuxième ligne de défense. `cle_combinaison`, description,
taux ou montant déjà converti ne sont pas des champs acceptés.

Une variante classifiée ne change jamais de combinaison. Renommer un libellé
ne change ni IDs ni clé. Le libellé de variante suit l'ordre des options et est
régénéré depuis les valeurs (`Noir / M`), maximum 255 caractères ; un renommage
qui dépasserait cette limite est refusé atomiquement. Les snapshots des
commandes ne sont jamais mis à jour. Le panier conserve `id_variante`.

## Transactions, concurrence, plafond et stock

Le service possède sa transaction (imbrication refusée), revalide les droits,
verrouille **le produit** `FOR UPDATE`, puis ses variantes par ID croissant.
Toutes les mutations du moteur, y compris définitions/renommages, prennent ce
même verrou produit. Un comptage des variantes verrouillées + créations du lot
refuse plus de **100**, actives et inactives comprises. Deux créations à 99
sont sérialisées ; une seule peut atteindre 100. Le chemin 3A crée seulement
la première variante d'un nouveau produit, jamais une variante additionnelle
sur un produit existant. Un futur autre chemin d'écriture devra respecter ce
verrou ; SQL direct n'est pas une API métier.

Le stock reste la quantité disponible **après réservations**. Pour modifier un
stock existant, le futur formulaire doit envoyer `stock_attendu` (la valeur
effectivement lue) et le nouveau `stock`. Sous verrou variante, toute différence
avec le stock actuel produit un conflit 409, sans écriture partielle. Omettre
`stock` conserve le stock verrouillé, même lors d'une modification de prix.
Les réservations/restitutions existantes prennent le verrou de cette même ligne
et font leurs incréments/décréments habituels. Aucun mouvement comptable de stock
ni nouvelle table de journal n'est introduit. Le contrôle porte sur la valeur
du stock, pas sur une version de formulaire universelle : un cycle revenant à
la même quantité n'est pas détecté. Une journalisation exhaustive/version de
stock serait un chantier distinct. Définir un stock disponible n'efface aucune
réservation ; sa restitution future s'ajoutera toujours normalement. Le plafond
SQL du stock conserve la place des quantités encore restituables par les
commandes en attente de paiement, confirmées ou en préparation ; un remplacement
extrême qui ferait déborder une future restitution est refusé.

Deadlock/timeout : rollback complet et conflit 409 explicite, pas de retry
silencieux d'une intention possiblement obsolète. Une collision SKU/clé/libellé
UNIQUE devient une erreur métier 422. Une création rejouée avec la même
combinaison est refusée, sans doublon ; une modification par ID est stable.
Un retry de remplacement de stock périmé demande relecture, pas un second
écrasement. Les clés d'idempotence S1.7/S1.9 ne sont pas modifiées.

## Prix partagé et limites volontaires

`ProductVariantInput` est la source commune des règles de SKU et de prix de
3A/4B (même générateur aléatoire `TYE-` + 16 hex, contrainte UNIQUE globale).
La conversion CDF appelle uniquement `MoneyService::cdfToUsd` et stocke USD.
Une ancienne devise NULL ne devient jamais USD sans saisie explicite du prix.
Taux indisponible : refus de la saisie CDF, jamais de taux de secours.

Restent à 4C/5 : formulaires, CSRF des nouvelles routes, choix explicite de la
première combinaison, édition ergonomique des options, sélection client,
retours de conflits utilisateur. Ni images par variante, ni promotion, ni
matérialisation cartésienne, ni migration réelle dans cette phase.
