# Informations du document

**Projet :** Tout y est  
**Document :** MCD V1  
**Version :** 1.0  
**Statut :** Validé  
**Auteur :** Équipe du projet  
**Dernière mise à jour :** AAAA-MM-JJ  
**Document suivant :** 02_MLD_V1.md

---

# 01_MCD_V1.md

# 🏛️ Modèle Conceptuel de Données (MCD) – Version 1

## Projet : Tout y est

Version : V1  
Statut : ✅ Validé  
Dernière mise à jour : (à compléter)

---

# 📖 Objectif

Ce document décrit le **Modèle Conceptuel de Données (MCD)** du projet **Tout y est**.

Le MCD représente les besoins métier de l'application.

Il ne décrit :

- ni le SQL ;
- ni les types de données ;
- ni les clés étrangères ;
- ni l'implémentation PHP.

Son objectif est uniquement de représenter les entités métier et leurs relations.

---

# 🎯 Principes

Le modèle respecte les principes suivants :

- simplicité ;
- cohérence métier ;
- évolutivité ;
- absence de redondance ;
- indépendance vis-à-vis de la technologie.

Toute évolution doit préserver ces principes.

---

# 📦 Entités principales

Le système repose principalement sur les entités suivantes :

- Utilisateur
- Catégorie
- Produit
- VarianteProduit
- Promotion
- Panier
- LignePanier
- Commande
- LigneCommande
- Paiement
- ModePaiement
- Livraison
- ZoneLivraison

---

# 🔗 Relations métier

## Utilisateur

Un utilisateur peut posséder :

- plusieurs commandes ;
- plusieurs paiements ;
- plusieurs livraisons ;
- un seul panier actif.

---

## Catégorie

Une catégorie contient plusieurs produits.

Un produit appartient à une seule catégorie.

---

## Produit

Un produit possède au minimum une variante.

Même lorsqu'il n'existe qu'une seule version commerciale.

Le produit représente uniquement l'objet vendu.

Les informations variables sont portées par les variantes.

---

## VarianteProduit

Une variante appartient à un seul produit.

Elle contient notamment :

- le prix de référence ;
- le stock ;
- le SKU ;
- les caractéristiques commerciales.

Les variantes permettent de gérer par exemple :

- tailles ;
- couleurs ;
- capacités ;
- modèles.

---

## Promotion

Une promotion peut s'appliquer à plusieurs produits.

Pour la V1 :

Une seule promotion active est autorisée par produit à un instant donné.

---

## Panier

Le panier représente les articles sélectionnés par un utilisateur connecté.

Un utilisateur possède un seul panier actif.

Le panier invité n'est pas représenté dans ce MCD car il est conservé uniquement dans le navigateur.

---

## LignePanier

Une ligne de panier représente :

une variante

-

une quantité.

Une ligne correspond toujours à une seule variante.

---

## Commande

Une commande est créée lors de la validation du panier.

Une commande contient plusieurs lignes de commande.

Une commande conserve son historique même si le catalogue évolue.

---

## LigneCommande

Une ligne de commande contient un snapshot des informations essentielles :

- nom du produit ;
- SKU ;
- prix ;
- informations nécessaires à la conservation de l'historique.

Les lignes de commande ne doivent jamais dépendre des modifications ultérieures du catalogue.

---

## Paiement

Une commande peut avoir plusieurs tentatives de paiement.

Une seule tentative peut être validée.

L'historique des tentatives est conservé.

---

## ModePaiement

Les moyens de paiement sont indépendants des paiements.

Ils permettent d'ajouter facilement de nouveaux moyens de paiement sans modifier l'architecture.

---

## Livraison

Chaque commande possède une livraison.

La livraison appartient à une zone de livraison.

---

## ZoneLivraison

Les zones permettent de définir :

- les frais ;
- les délais ;
- les règles de livraison.

---

# 📋 Règles métier

Les règles suivantes sont considérées comme officielles.

## Produits

- Un produit possède au moins une variante.

---

## Variantes

- Une variante appartient à un seul produit.

---

## Prix

Le prix appartient à la variante.

Jamais au produit.

---

## Panier

Le panier ne stocke jamais les prix.

Les prix sont recalculés lors de chaque consultation.

---

## Promotions

Une seule promotion active par produit pour la V1.

---

## Commandes

Les commandes utilisent des snapshots.

Le catalogue ne doit jamais modifier une commande déjà validée.

---

## Paiements

Plusieurs tentatives sont autorisées.

Une seule est validée.

---

## Historique

Aucune suppression ne doit casser l'historique.

Les données importantes sont archivées.

---

# 🚫 Hors périmètre

Les éléments suivants ne font pas partie du MCD V1 :

- moteur de recherche ;
- cache ;
- favoris ;
- recommandations ;
- notifications ;
- statistiques ;
- logs techniques.

Ils seront étudiés séparément.

---

# 📈 Évolutions prévues

L'architecture permet d'ajouter ultérieurement :

- plusieurs entrepôts ;
- plusieurs transporteurs ;
- plusieurs promotions cumulables ;
- plusieurs adresses de livraison ;
- plusieurs devises ;
- plusieurs langues.

Sans remise en cause du modèle conceptuel.

---

# ✅ Validation

Ce MCD constitue la référence officielle du projet.

Toute modification doit être :

- documentée ;
- justifiée ;
- validée avant implémentation.
