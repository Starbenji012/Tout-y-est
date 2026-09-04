# 00_LIRE_EN_PREMIER.md

# 🛒 TOUT Y EST

## Guide de démarrage du projet

Bienvenue dans **Tout y est**.

Ce projet est développé selon une approche d'ingénierie logicielle progressive.

Avant toute modification, chaque développeur ou IA doit lire les documents d'architecture afin de comprendre les décisions déjà validées.

Le but est d'assurer la cohérence du projet et d'éviter les régressions.

---

# 📌 Objectif du projet

Tout y est est une plateforme e-commerce moderne développée avec une architecture MVC en PHP orienté objet.

Les objectifs principaux sont :

- simplicité d'utilisation ;
- performance ;
- évolutivité ;
- sécurité ;
- maintenabilité.

Chaque évolution doit respecter ces objectifs.

---

# 📚 Ordre de lecture obligatoire

Avant d'écrire ou modifier du code, lire les documents dans l'ordre suivant :

1. `00_LIRE_EN_PREMIER.md`
2. `01_MCD_V1.md`
3. `02_MLD_V1.md`
4. `03_SQL_V1.md`
5. `04_PLAN_MIGRATION.md`
6. `05_DECISIONS_ARCHITECTURE.md`
7. `06_ROADMAP.md`
8. `07_REGLES_DEVELOPPEMENT.md`
9. `08_GLOSSAIRE.md`

---

# 📖 Signification des documents

## 01_MCD_V1.md

Décrit les besoins métier.

Il explique :

- les entités ;
- les associations ;
- les règles métier.

Aucune implémentation technique.

---

## 02_MLD_V1.md

Traduit le MCD en modèle logique.

Décrit :

- les tables ;
- les clés ;
- les relations.

---

## 03_SQL_V1.md

Contient le schéma SQL officiel.

Toute évolution de la base doit partir de ce document.

---

## 04_PLAN_MIGRATION.md

Explique :

- comment migrer la base ;
- dans quel ordre ;
- les risques ;
- les vérifications.

---

## 05_DECISIONS_ARCHITECTURE.md

Recense toutes les décisions importantes prises pendant la conception.

Ce document explique **pourquoi** certaines solutions ont été retenues.

---

## 06_ROADMAP.md

Présente :

- les modules terminés ;
- les modules en cours ;
- les prochaines étapes.

---

## 07_REGLES_DEVELOPPEMENT.md

Décrit les règles de développement à respecter.

---

## 08_GLOSSAIRE.md

Décrit les principaux termes utilisés dans le projet.

---

# 🧠 Méthode de travail

Chaque nouvelle fonctionnalité suit le cycle suivant :

Analyse

↓

Architecture

↓

Validation

↓

Développement

↓

Tests

↓

Correction

↓

Validation finale

Aucune fonctionnalité ne doit être développée sans respecter ce processus.

---

# 🤖 Répartition des rôles

## Codex

Utilisé pour :

- architecture ;
- conception ;
- analyse ;
- audit ;
- revue technique ;
- validation.

---

## GitHub Copilot

Utilisé pour :

- implémentation ;
- corrections ;
- optimisation ;
- tests.

---

# ⚠️ Règles importantes

- Ne jamais modifier le MCD sans validation.
- Ne jamais modifier le MLD sans validation.
- Ne jamais modifier le SQL officiel sans validation.
- Respecter l'architecture MVC.
- Réutiliser le code existant avant de créer un nouveau composant.
- Éviter toute duplication de logique métier.

---

# 📋 En cas d'incohérence

Si une incohérence est détectée :

1. Ne pas la corriger immédiatement.
2. La documenter.
3. Expliquer son impact.
4. Proposer une solution.
5. Attendre validation avant implémentation.

---

# 🎯 Philosophie du projet

Les priorités sont :

1. Qualité de l'architecture.
2. Simplicité.
3. Maintenabilité.
4. Expérience utilisateur.
5. Performance.
6. Sécurité.

Une solution plus simple et plus robuste est toujours préférable à une solution plus complexe.

---

# 🚀 Objectif

Construire une plateforme e-commerce robuste, évolutive et maintenable.

Chaque contribution doit améliorer le projet sans compromettre les décisions d'architecture déjà validées.
