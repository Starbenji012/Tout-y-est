# Design Language — Tout y est

Ce document est la référence visuelle de la version 1. Le logo `Public/assets/images/branding/logo.png` guide toutes les décisions graphiques. Un composant doit prolonger son univers visuel sans le concurrencer.

## Design System figé

Le socle visuel de la version 1 est officiellement figé. Sa structure respecte une responsabilité par fichier :

| Fichier | Responsabilité |
|---|---|
| `variables.css` | Palette, typographie, espacements et tokens sémantiques |
| `reset.css` | Normalisation des styles natifs du navigateur |
| `global.css` | Styles globaux et présentation des composants réutilisables |
| `animations.css` | Animations partagées et préférence de mouvement réduit |
| `utilities.css` | Classes atomiques de mise en page, visibilité et effets |

L'ordre de chargement doit rester : variables, reset, global, animations, utilitaires, puis styles propres aux composants ou aux pages. Toute évolution du Design System doit répondre à un besoin partagé par plusieurs composants.

## Palette

L'échelle Brand Gray est issue des niveaux de gris présents dans le logo officiel. `--brand-gray-500` est la référence principale de la marque ; les nuances plus claires ou plus foncées servent uniquement à créer la hiérarchie nécessaire.

| Nuance | Token | Valeur | Usage principal |
|---|---|---|---|
| Brand Gray 50 | `--brand-gray-50` | `#fbfbfb` | Sections très légères |
| Brand Gray 100 | `--brand-gray-100` | `#f7f7f7` | Surfaces secondaires discrètes |
| Brand Gray 200 | `--brand-gray-200` | `#efefef` | Cartes, formulaires et champs |
| Brand Gray 300 | `--brand-gray-300` | `#e7e7e7` | Séparateurs légers |
| Brand Gray 400 | `--brand-gray-400` | `#d6d6d6` | Bordures standards |
| Brand Gray 500 | `--brand-gray-500` | `#b9b9b9` | Gris de marque de référence |
| Brand Gray 600 | `--brand-gray-600` | `#979797` | Éléments secondaires renforcés |
| Brand Gray 700 | `--brand-gray-700` | `#787878` | Bordures fortes et états inactifs |
| Brand Gray 800 | `--brand-gray-800` | `#303030` | Texte secondaire accessible |
| Brand Gray 900 | `--brand-gray-900` | `#1d1d1d` | Anthracite, texte et icônes |

Le blanc pur est exclu de l'interface. Brand Gray 200 est le fond principal ; Brand Gray 50 et 100 fournissent uniquement les contrastes clairs nécessaires. Brand Gray 900 et l'anthracite portent les contenus prioritaires. Le doré reste un accent réservé aux appels à l'action, promotions, badges et états actifs ; il ne couvre jamais une grande surface.

Tout gris employé dans une feuille de style doit provenir d'un token Brand Gray ou d'un token sémantique défini dans `variables.css`. Une valeur grise écrite directement dans un composant est interdite.

## Typographie

La police unique est Poppins. La hiérarchie repose sur les tokens de `variables.css` :

| Élément | Taille |
|---|---|
| Titre H1 | `clamp(2.25rem, 5vw, 3.5rem)` |
| Titre H2 | `clamp(1.875rem, 4vw, 2.75rem)` |
| Titre H3 | `clamp(1.5rem, 3vw, 2rem)` |
| Titre H4 | `1.25rem` |
| Titre H5 | `1.125rem` |
| Titre H6 | `1rem` |
| Sous-titre | `clamp(1.125rem, 2vw, 1.5rem)` |
| Paragraphe | `1rem` |
| Bouton et label | `0.875rem` |

Les titres et paragraphes sont anthracite, tandis que les informations secondaires utilisent les Brand Gray. Utiliser une graisse 700 pour les titres principaux, 600 pour les sous-titres et actions, puis 400 pour le texte courant.

## Boutons

Tous les boutons réutilisent `.btn` avec une variante :

- hauteur minimale : `3rem` ;
- largeur minimale : `7rem` ;
- rayon : `0.5rem` ;
- espacement horizontal : `1.5rem` ;
- bouton principal : fond doré et texte anthracite ;
- bouton secondaire : fond Brand Gray et texte anthracite ;
- survol : déplacement vertical maximal de `1px` ;
- état actif : retour à la position initiale ;
- état désactivé : opacité réduite, interaction et pointeur désactivés.

Un écran ne doit présenter qu'une action principale dominante par zone fonctionnelle.

## Formulaires

La recherche, la connexion, l'inscription, le paiement et les adresses utilisent les styles globaux de `global.css` :

- hauteur minimale : `3rem` ;
- fond Brand Gray 200 au repos et Brand Gray 100 au focus ;
- bordure grise de `1px` ;
- rayon de `0.5rem` ;
- padding de `0.75rem 1rem` ;
- focus doré avec anneau visible ;
- label de `0.875rem`, graisse 500 ;
- message d'aide placé sous le champ avec le gris atténué.

Chaque champ possède un label visible, sauf lorsqu'un label accessible masqué est justifié. Les placeholders ne remplacent jamais les labels.

## Cartes

Les produits, catégories, avis et promotions partagent la base `.card` :

- fond gris clair ;
- bordure grise de `1px` ;
- rayon de `0.75rem` ;
- padding interne de `1.5rem` ;
- ombre légère par défaut ;
- espacement externe géré par le conteneur parent, jamais par la carte.

Une carte interactive peut renforcer légèrement son ombre au survol. Une carte informative reste immobile.

## Icônes

Lucide est la bibliothèque de référence :

- petite : `1rem` ;
- standard : `1.25rem` ;
- grande : `1.5rem` ;
- épaisseur : `1.8` ;
- couleur par défaut : anthracite ;
- doré uniquement pour un état actif ou prioritaire.

Les icônes décoratives utilisent `aria-hidden="true"`. Un bouton composé uniquement d'une icône possède toujours un libellé ARIA.

## Ombres

- légère : cartes et champs actifs ;
- moyenne : Header sticky, menus et panneaux élevés ;
- forte : modales, tiroirs et overlays uniquement.

Ne jamais cumuler plusieurs ombres sur le même composant.

## Espacements et conteneurs

La grille principale utilise `8px`, `16px`, `24px`, `32px`, `48px` et `64px`. Les valeurs de `4px` et `12px` sont réservées aux micro-espacements internes.

Chaque élément majeur possède un conteneur dédié. Le conteneur contrôle dimensions, alignement, espacement et responsive ; l'élément contrôle uniquement son contenu.

Les sections utilisent `.section` et les contenus utilisent `.container`. Les marges entre composants sont gérées par `gap` sur le conteneur parent.

## Responsive

- mobile : jusqu'à `48rem` ;
- tablette : de `48rem` à `64rem` ;
- desktop : au-delà de `64rem`.

Règles communes :

- conserver une zone tactile minimale de `44px` ;
- éviter tout défilement horizontal ;
- empiler les groupes avant de réduire leur lisibilité ;
- conserver le ratio des images et du logo ;
- utiliser les tailles fluides définies par `clamp()` ;
- masquer uniquement les informations secondaires ;
- tester chaque composant aux trois paliers avant validation.

## Validation d'un composant

Avant validation, vérifier sa cohérence avec le logo, son usage exclusif des gris de la marque, sa hiérarchie typographique, ses conteneurs, ses états interactifs, son accessibilité et son comportement responsive. Toute exception doit répondre à un besoin fonctionnel réel et être documentée.
