# Informations du document

**Projet :** Tout y est  
**Document :** Décisions d'Architecture  
**Version :** 1.0  
**Statut :** Validé  
**Auteur :** Équipe du projet  
**Dernière mise à jour :** AAAA-MM-JJ  
**Document précédent :** 04_PLAN_MIGRATION.md  
**Document suivant :** 06_ROADMAP.md

---

# 🏗️ Décisions d'Architecture

## Objectif

Ce document recense toutes les décisions d'architecture officiellement validées pendant la conception du projet **Tout y est**.

Son objectif est de conserver les raisons ayant conduit à ces choix afin de garantir la cohérence du projet dans le temps.

En cas de doute, ce document fait référence.

---

# Philosophie générale

Le projet privilégie toujours :

1. la simplicité ;
2. la maintenabilité ;
3. la robustesse ;
4. l'expérience utilisateur ;
5. l'évolutivité.

Une solution simple, claire et fiable est toujours préférée à une solution plus complexe.

---

# Décisions validées

## Architecture

### MVC

Le projet utilise une architecture MVC en PHP orienté objet.

Cette architecture est conservée pour l'ensemble de la plateforme.

Aucun développement ne doit contourner cette architecture.

---

## Réutilisation

Avant de créer un nouveau composant :

- vérifier qu'un composant existant ne peut pas être réutilisé ;
- éviter toute duplication de logique métier.

---

# Base de données

## Catégorie

La catégorie est une véritable entité.

Elle n'est pas représentée par un simple champ texte.

---

## Produit

Le produit représente uniquement l'objet commercial.

Il ne porte pas les informations variables.

---

## Variante

Toutes les informations variables appartiennent à la variante.

Exemples :

- prix ;
- stock ;
- SKU ;
- caractéristiques.

Même un produit possédant une seule version possède une variante.

---

## Prix

Le prix de référence appartient toujours à la variante.

Cette décision permet de gérer naturellement :

- tailles ;
- couleurs ;
- capacités ;
- modèles.

---

# Panier

## Panier invité

Le panier invité est stocké dans le navigateur (`localStorage`).

Cette décision :

- évite une complexité inutile ;
- améliore les performances ;
- facilite l'expérience utilisateur.

---

## Panier utilisateur

Le panier utilisateur est enregistré en base.

Chaque utilisateur possède un seul panier actif.

---

## Fusion des paniers

Lorsqu'un utilisateur se connecte :

- les paniers sont fusionnés automatiquement ;
- les quantités sont additionnées dans la limite du stock ;
- aucune fenêtre de confirmation n'est affichée.

Une notification discrète informe simplement l'utilisateur.

---

## Sauvegarde

Toute modification du panier est enregistrée immédiatement.

Aucune action utilisateur ne doit être perdue.

---

# Promotions

Une seule promotion active est autorisée par produit dans la Version 1.

Cette décision simplifie :

- le calcul des prix ;
- les tests ;
- la maintenance.

Les promotions multiples pourront être ajoutées dans une version future.

---

# Commandes

Les commandes utilisent des snapshots.

Les informations importantes sont figées au moment de la validation.

Ainsi :

- les modifications du catalogue n'affectent jamais les commandes passées.

---

# Paiements

Une commande peut posséder plusieurs tentatives de paiement.

Une seule tentative est considérée comme valide.

Toutes les tentatives sont conservées dans l'historique.

---

# Stock

Le stock affiché au client suit les règles suivantes :

- En stock
- Plus que 5 disponibles
- Rupture de stock

Le stock exact supérieur à 5 n'est jamais affiché.

---

# Produits indisponibles

Les produits indisponibles restent visibles dans le panier.

Le client peut :

- attendre leur retour ;
- les supprimer.

Ils ne sont jamais supprimés automatiquement.

---

# Authentification

Le catalogue et le panier restent accessibles sans connexion.

La connexion devient obligatoire uniquement lors de la validation de la commande.

---

# Expérience utilisateur

Les interfaces privilégient :

- simplicité ;
- rapidité ;
- lisibilité.

Les fenêtres de confirmation inutiles sont évitées.

Les notifications sont discrètes.

Le parcours utilisateur est prioritaire.

---

# Performances

Les optimisations sont réalisées uniquement lorsqu'elles sont justifiées par des mesures.

Le projet évite les optimisations prématurées.

---

# Sécurité

Les décisions suivantes sont obligatoires :

- validation côté serveur ;
- requêtes préparées ;
- protection CSRF ;
- contrôle des droits d'accès ;
- validation des données.

---

# Documentation

Toute décision importante doit être documentée avant d'être implémentée.

Le code ne doit jamais être la seule source d'information.

---

# Évolutions futures

Les décisions suivantes sont volontairement reportées :

- promotions cumulables ;
- plusieurs devises ;
- plusieurs langues ;
- plusieurs adresses utilisateur ;
- favoris avancés ;
- moteur de recherche spécialisé ;
- cache distribué.

Ces évolutions ne doivent pas remettre en cause les principes fondamentaux de l'architecture.

---

# Validation

Toute modification de ce document doit être :

- documentée ;
- justifiée ;
- validée avant développement.

Ce document constitue la mémoire officielle des décisions d'architecture du projet.
