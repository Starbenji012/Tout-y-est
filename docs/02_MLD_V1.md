# Informations du document

**Projet :** Tout y est  
**Document :** MLD V1  
**Version :** 1.0  
**Statut :** Validé  
**Auteur :** Équipe du projet  
**Dernière mise à jour :** AAAA-MM-JJ  
**Document précédent :** 01_MCD_V1.md  
**Document suivant :** 03_SQL_V1.md

---

# 📘 Modèle Logique de Données (MLD) – Version 1

## Objectif

Ce document décrit le Modèle Logique de Données (MLD) du projet **Tout y est**.

Le MLD traduit le MCD en une structure relationnelle prête à être convertie en SQL.

Il définit :

- les tables ;
- les clés primaires ;
- les clés étrangères ;
- les contraintes logiques.

Il ne décrit pas encore les types SQL.

---

# Principes

Le MLD est fidèle au MCD validé.

Aucune nouvelle règle métier ne doit apparaître dans ce document.

Toute modification doit d'abord être validée au niveau du MCD.

---

# Tables principales

Le modèle est composé des tables suivantes :

- UTILISATEUR
- CATEGORIE
- PRODUIT
- VARIANTE_PRODUIT
- PROMOTION
- PANIER
- LIGNE_PANIER
- COMMANDE
- LIGNE_COMMANDE
- MODE_PAIEMENT
- PAIEMENT
- LIVRAISON
- ZONE_LIVRAISON

---

# Relations principales

## UTILISATEUR

Possède :

- un panier actif ;
- plusieurs commandes.

---

## CATEGORIE

Contient plusieurs produits.

---

## PRODUIT

Appartient à une catégorie.

Possède plusieurs variantes.

---

## VARIANTE_PRODUIT

Appartient à un produit.

Est utilisée dans :

- les lignes de panier ;
- les lignes de commande.

Le prix de référence est porté par cette table.

---

## PANIER

Appartient à un utilisateur.

Contient plusieurs lignes.

---

## LIGNE_PANIER

Référence :

- un panier ;
- une variante.

Une contrainte d'unicité empêche deux lignes identiques pour une même variante dans un même panier.

---

## COMMANDE

Appartient à un utilisateur.

Contient plusieurs lignes de commande.

---

## LIGNE_COMMANDE

Référence :

- une commande ;
- une variante.

Contient les snapshots nécessaires :

- nom du produit ;
- SKU ;
- prix au moment de l'achat.

---

## PROMOTION

Peut être associée à plusieurs produits.

Pour la V1 :

Un seul produit ne peut bénéficier que d'une seule promotion active à un instant donné.

---

## PAIEMENT

Référence :

- une commande ;
- un mode de paiement.

Plusieurs tentatives sont autorisées.

---

## MODE_PAIEMENT

Décrit les moyens de paiement disponibles.

---

## LIVRAISON

Référence :

- une commande ;
- une zone de livraison.

---

## ZONE_LIVRAISON

Détermine :

- les frais ;
- les délais.

---

# Contraintes logiques

Le modèle applique notamment :

- une seule ligne par variante dans un panier ;
- un seul panier actif par utilisateur ;
- au moins une variante par produit ;
- une seule promotion active par produit (V1) ;
- conservation de l'historique des commandes.

---

# Normalisation

Le modèle respecte les principes de normalisation jusqu'à la troisième forme normale (3FN).

Les redondances métier sont évitées.

Les informations figées nécessaires à l'historique sont volontairement conservées sous forme de snapshots.

---

# Compatibilité

Ce MLD est compatible avec :

- le schéma SQL V1 ;
- l'architecture MVC du projet ;
- les évolutions futures prévues.

---

# Validation

Ce document constitue la référence logique officielle.

Toute évolution doit être validée avant d'être répercutée sur le schéma SQL ou le code applicatif.
