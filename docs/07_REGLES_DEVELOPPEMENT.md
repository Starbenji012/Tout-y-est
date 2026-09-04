# Informations du document

**Projet :** Tout y est  
**Document :** Règles de Développement  
**Version :** 1.0  
**Statut :** Validé  
**Auteur :** Équipe du projet  
**Dernière mise à jour :** AAAA-MM-JJ  
**Document précédent :** 06_ROADMAP.md  
**Document suivant :** 08_GLOSSAIRE.md

---

# 📖 Règles de Développement

## Objectif

Ce document définit les règles officielles de développement du projet **Tout y est**.

Chaque développeur ou intelligence artificielle intervenant sur le projet doit respecter ces règles afin de garantir :

- la cohérence du code ;
- la qualité de l'architecture ;
- la maintenabilité ;
- la sécurité ;
- l'évolutivité.

---

# 1. Architecture

Le projet repose sur une architecture **MVC (Model - View - Controller)**.

Chaque couche possède une responsabilité unique.

- **Model** : accès aux données.
- **Service** : logique métier.
- **Controller** : traitement des requêtes.
- **View** : affichage.
- **JavaScript** : interactions côté client uniquement.

La logique métier ne doit jamais être placée dans les vues.

---

# 2. Réutilisation du code

Avant de créer un nouveau composant :

- vérifier qu'un composant similaire n'existe pas déjà ;
- privilégier la réutilisation ;
- éviter les duplications.

Le principe **DRY (Don't Repeat Yourself)** doit être respecté.

---

# 3. Base de données

Toute modification de la base doit respecter :

- le MCD validé ;
- le MLD validé ;
- le schéma SQL officiel.

Aucune modification ne doit être réalisée directement sans validation préalable.

---

# 4. Sécurité

Toutes les fonctionnalités doivent respecter les règles suivantes :

- validation côté serveur ;
- requêtes préparées ;
- protection CSRF ;
- contrôle des permissions ;
- échappement des données affichées.

Les données provenant de l'utilisateur ne doivent jamais être considérées comme fiables.

---

# 5. Qualité du code

Le code doit être :

- lisible ;
- simple ;
- documenté lorsque nécessaire ;
- facilement maintenable.

Les fonctions trop longues doivent être découpées.

Les noms doivent être explicites.

---

# 6. Front-end

Respecter le Design System existant.

Ne pas :

- créer de nouvelles couleurs sans validation ;
- modifier les composants communs inutilement.

Le responsive est obligatoire.

Les interfaces doivent fonctionner sur :

- ordinateur ;
- tablette ;
- mobile.

---

# 7. JavaScript

Le JavaScript doit :

- rester modulaire ;
- éviter les variables globales ;
- utiliser les API modernes du navigateur lorsque cela est pertinent.

Les manipulations du DOM doivent être limitées au nécessaire.

---

# 8. Performances

Avant toute optimisation :

- mesurer ;
- identifier le problème ;
- proposer une solution.

Aucune optimisation prématurée.

---

# 9. Tests

Toute nouvelle fonctionnalité doit être testée.

Les tests doivent couvrir :

- le fonctionnement normal ;
- les cas limites ;
- les erreurs.

Les anciennes fonctionnalités ne doivent pas être dégradées.

---

# 10. Documentation

Toute décision importante doit être documentée.

Toute évolution majeure doit mettre à jour :

- la Roadmap ;
- les Décisions d'Architecture ;
- la documentation concernée.

---

# 11. Git

Les commits doivent être :

- petits ;
- cohérents ;
- explicites.

Exemple :

- feat: ajout du panier connecté
- fix: correction de la fusion des paniers
- refactor: simplification du CartService

Éviter les messages vagues comme :

- update
- correction
- test

---

# 12. Travail avec les IA

## Codex

Utiliser Codex pour :

- architecture ;
- conception ;
- audit ;
- revue technique ;
- validation.

---

## GitHub Copilot

Utiliser Copilot pour :

- implémentation ;
- correction ;
- optimisation ;
- génération de code.

---

## Règle

Aucune IA ne doit modifier l'architecture validée sans justification.

En cas de doute :

- documenter ;
- proposer ;
- attendre validation.

---

# 13. Philosophie du projet

Le projet privilégie toujours :

1. Simplicité.
2. Robustesse.
3. Maintenabilité.
4. Évolutivité.
5. Expérience utilisateur.
6. Sécurité.

Une solution plus simple et plus claire est toujours préférable à une solution plus complexe.

---

# Validation

Ce document constitue le guide officiel de développement du projet.

Toute personne intervenant sur le projet doit respecter ces règles.
