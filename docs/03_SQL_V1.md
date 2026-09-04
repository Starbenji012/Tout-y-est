# Informations du document

**Projet :** Tout y est  
**Document :** Schéma SQL V1  
**Version :** 1.0  
**Statut :** Validé  
**Auteur :** Équipe du projet  
**Dernière mise à jour :** AAAA-MM-JJ  
**Document précédent :** 02_MLD_V1.md  
**Document suivant :** 04_PLAN_MIGRATION.md

---

# 🗄️ Schéma SQL – Version 1

## Objectif

Ce document décrit le schéma SQL officiel de la Version 1 du projet **Tout y est**.

Le schéma SQL est la traduction physique du MLD validé.

Il constitue la référence pour :

- la création de la base de données ;
- les migrations ;
- les modèles PHP ;
- les contraintes d'intégrité.

---

# Principes

Le schéma SQL respecte intégralement :

- le MCD validé ;
- le MLD validé.

Aucune règle métier ne doit être ajoutée directement dans le SQL sans validation préalable.

---

# Tables principales

Le schéma comprend notamment les tables suivantes :

- utilisateur
- categorie
- produit
- variante_produit
- promotion
- panier
- ligne_panier
- commande
- ligne_commande
- mode_paiement
- paiement
- livraison
- zone_livraison

---

# Principes de conception

## Clés primaires

Chaque table possède une clé primaire unique.

Les identifiants sont utilisés uniquement comme clés techniques.

---

## Clés étrangères

Toutes les relations entre tables sont protégées par des clés étrangères.

Le schéma privilégie l'intégrité référentielle.

---

## Contraintes

Les contraintes SQL garantissent notamment :

- l'unicité des lignes de panier par variante ;
- la cohérence des références ;
- la validité des relations.

---

## Index

Les index sont créés uniquement lorsqu'ils apportent un bénéfice réel.

Ils concernent principalement :

- les clés étrangères ;
- les colonnes fréquemment recherchées ;
- les contraintes d'unicité.

Aucun index n'est ajouté sans justification.

---

# Gestion des prix

Le prix de référence appartient à la table :

variante_produit

Le panier ne stocke jamais les prix.

Les prix sont recalculés à partir des données du catalogue.

---

# Promotions

Les promotions sont indépendantes du panier.

Pour la Version 1 :

- une seule promotion active par produit.

---

# Commandes

Les lignes de commande utilisent des snapshots.

Ces informations garantissent la conservation de l'historique.

Les modifications ultérieures du catalogue n'ont aucun impact sur les commandes déjà validées.

---

# Paiements

Une commande peut posséder plusieurs tentatives de paiement.

Une seule tentative peut être validée.

---

# Livraison

Chaque commande possède une livraison.

Les frais sont calculés à partir de la zone de livraison.

---

# Intégrité référentielle

Le schéma privilégie :

- la cohérence des données ;
- l'historique ;
- la conservation des références.

Les suppressions physiques sont limitées.

L'archivage est préféré lorsque cela est possible.

---

# Compatibilité

Le schéma est compatible avec :

- MySQL 8 ;
- l'architecture MVC du projet ;
- les futurs modules de la plateforme.

---

# Évolutions prévues

Le schéma a été conçu pour faciliter l'ajout futur de :

- plusieurs adresses utilisateur ;
- plusieurs devises ;
- plusieurs langues ;
- plusieurs transporteurs ;
- plusieurs promotions avancées ;
- nouveaux moyens de paiement.

---

# Validation

Ce document constitue la référence SQL officielle du projet.

Toute modification doit être :

- documentée ;
- justifiée ;
- validée avant implémentation.

Le SQL doit toujours rester fidèle au MLD validé.
