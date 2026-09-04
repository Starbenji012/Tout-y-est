# Informations du document

**Projet :** Tout y est  
**Document :** Glossaire  
**Version :** 1.0  
**Statut :** Validé  
**Auteur :** Équipe du projet  
**Dernière mise à jour :** AAAA-MM-JJ  
**Document précédent :** 07_REGLES_DEVELOPPEMENT.md

---

# 📖 Glossaire

## Objectif

Ce glossaire définit les principaux termes utilisés dans le projet **Tout y est**.

Il permet à toute personne intervenant sur le projet de comprendre immédiatement le vocabulaire utilisé dans la documentation et le code.

Les définitions présentées ici constituent la référence officielle.

---

# A

## Architecture

Organisation générale du projet permettant de séparer les responsabilités entre les différentes couches de l'application.

---

# C

## Catégorie

Famille de produits.

Chaque produit appartient à une seule catégorie.

Exemple :

- Téléphones
- Chaussures
- Électronique

---

## Checkout

Dernière étape du tunnel d'achat.

Le client vérifie son panier, choisit son mode de livraison, son mode de paiement puis valide sa commande.

---

## Commande

Validation définitive d'un panier.

Une commande possède :

- plusieurs lignes de commande ;
- un paiement ;
- une livraison.

---

# D

## Design System

Ensemble des règles graphiques utilisées dans toute l'application :

- couleurs ;
- typographie ;
- boutons ;
- formulaires ;
- espacements ;
- composants.

---

# F

## Fusion des paniers

Processus consistant à réunir :

- le panier invité ;
- le panier utilisateur.

Cette opération est réalisée automatiquement lors de la connexion.

---

# L

## Ligne de commande

Élément représentant un produit acheté dans une commande.

Chaque ligne contient également un snapshot des informations nécessaires à l'historique.

---

## Ligne de panier

Élément représentant une variante et une quantité dans un panier.

Une variante ne peut apparaître qu'une seule fois dans un même panier.

---

# M

## MCD

Modèle Conceptuel de Données.

Décrit les besoins métier du projet sans dépendre d'une technologie particulière.

---

## MLD

Modèle Logique de Données.

Transforme le MCD en structure relationnelle.

---

## MVC

Architecture composée de :

- Model
- View
- Controller

Le projet utilise également une couche Service pour centraliser la logique métier.

---

# P

## Panier invité

Panier conservé uniquement dans le navigateur (`localStorage`).

Aucune donnée n'est enregistrée dans la base.

---

## Panier utilisateur

Panier enregistré dans la base de données et associé à un compte utilisateur.

---

## Produit

Objet commercial vendu sur la plateforme.

Le produit regroupe les informations communes.

Les informations variables appartiennent aux variantes.

---

## Promotion

Réduction temporaire appliquée à un produit.

Dans la Version 1 :

une seule promotion active est autorisée par produit.

---

# S

## Service

Couche de l'application contenant la logique métier.

Les contrôleurs utilisent les services.

Les services utilisent les modèles.

---

## SKU

Code unique identifiant une variante.

Il est utilisé pour :

- le stock ;
- les commandes ;
- la logistique.

---

## Snapshot

Copie figée des informations d'un produit au moment de la commande.

Les snapshots permettent de conserver l'historique même si le catalogue est modifié.

---

## Stock

Quantité disponible d'une variante.

Le client voit uniquement :

- En stock
- Plus que 5 disponibles
- Rupture de stock

---

# U

## Utilisateur

Personne possédant un compte sur la plateforme.

Un utilisateur peut :

- posséder un panier ;
- passer plusieurs commandes ;
- effectuer plusieurs paiements.

---

# V

## Variante

Version spécifique d'un produit.

Une variante possède notamment :

- un prix ;
- un stock ;
- un SKU ;
- des caractéristiques.

Exemples :

Téléphone :

- 128 Go
- 256 Go

T-shirt :

- S
- M
- L

Chaque produit possède au minimum une variante.

---

# Z

## Zone de livraison

Secteur géographique utilisé pour calculer :

- les frais ;
- les délais.

---

# Validation

Ce glossaire constitue la référence officielle du vocabulaire utilisé dans le projet.

Toute nouvelle notion introduite dans le projet doit être ajoutée à ce document.
