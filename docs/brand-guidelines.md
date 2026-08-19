# Identité visuelle — Tout y est

Le fichier `logo.png` est la référence absolue de la marque. Les autres fichiers sont des adaptations officielles destinées à des supports précis. Ils ne remplacent jamais le logo principal.

## Versions officielles

| Fichier | Dimensions | Utilisation |
| --- | ---: | --- |
| `logo.png` | 500 × 500 px | Documents officiels, communication institutionnelle et supports marketing. |
| `logo-header.png` | 400 × 165 px | Supports web qui exigent une image raster unique. Le Header du site utilise désormais le Brand Component. |
| `logo-square.png` | 500 × 500 px | Réseaux sociaux, avatar, profil d'entreprise et application mobile. |
| `favicon.png` | 165 × 165 px | Onglets, raccourcis et favoris du navigateur. Cette version utilise uniquement le chariot. |

Les fichiers sont conservés dans `Public/assets/images/branding/`.

## Brand Component web

Le Brand Component est la représentation officielle de la marque dans les interfaces web. Il associe le chariot original transparent `cart-icon.png` au nom et au slogan rendus en texte HTML.

Il est utilisé dans le Header, le menu mobile, le Footer et, à terme, les interfaces de connexion et pages internes. Cette composition améliore la lisibilité et le responsive sans remplacer ni modifier le logo institutionnel.

Message de présentation recommandé : « Je ne change pas ton logo. Je l'adapte au support web pour qu'il soit plus lisible, plus professionnel et plus agréable sur toutes les tailles d'écran. Ton identité reste exactement la même. »

## Éléments immuables

Les éléments suivants doivent toujours rester identiques au logo principal :

- le dessin et les proportions du chariot ;
- le nom « Tout y est » ;
- le slogan « Le meilleur sur le marché » et son traitement typographique ;
- la typographie, les niveaux de gris et les effets visuels d'origine ;
- les positions relatives des éléments lorsqu'ils figurent dans la déclinaison.

Le favicon constitue l'unique exception autorisée à l'affichage du nom et du slogan, car leur lisibilité n'est pas possible à très petite taille.

## Palette du projet

La palette de l'interface est construite à partir des niveaux de gris du logo officiel :

| Famille | Couleurs |
| --- | --- |
| Fond principal | Brand Gray 200 `#efefef` |
| Brand Gray 50–400 | `#fbfbfb`, `#f7f7f7`, `#efefef`, `#e7e7e7`, `#d6d6d6` |
| Brand Gray 500 | Gris de référence `#b9b9b9` |
| Brand Gray 600–900 | `#979797`, `#787878`, `#303030`, `#1d1d1d` |
| Contraste fort | Anthracite, Brand Gray 900 `#1d1d1d` |
| Accent | Doré `#c8a33a` |
| Accent sombre | Doré sombre `#a7831f` |

Tous les gris de l'interface doivent utiliser les tokens `--brand-gray-50` à `--brand-gray-900` définis dans `variables.css`. Le blanc pur est interdit dans l'interface : Brand Gray 50 et 100 assurent les zones de lecture, les autres nuances structurent les composants, l'anthracite porte les textes, et le doré reste réservé aux accents, états actifs et actions importantes.

## Règles d'utilisation

- Utiliser chaque fichier uniquement pour le support auquel il est destiné.
- Conserver le ratio natif de l'image avec `width: auto`, `height: auto` ou `object-fit: contain`.
- Conserver les marges intégrées à chaque fichier et laisser un espace libre autour du logo.
- Utiliser le fichier principal ou carré à partir de 160 px de large lorsque le slogan doit rester lisible.
- Utiliser le logo Header à partir de 120 px de large.
- Utiliser le favicon aux tailles standards de 16, 32, 48 ou 180 px.

## Interdictions

- Ne pas étirer, compresser, incliner ou faire pivoter le logo.
- Ne pas modifier les couleurs, le contraste, les ombres ou la transparence.
- Ne pas redessiner le chariot, le texte ou le slogan.
- Ne pas remplacer la typographie.
- Ne pas déplacer les éléments internes ni modifier leurs proportions relatives.
- Ne pas ajouter de contour, d'effet, de fond coloré ou d'élément décoratif.
- Ne pas supprimer le nom ou le slogan en dehors du favicon officiel.
- Ne pas effectuer un nouveau recadrage à partir d'une déclinaison : toujours repartir du logo principal.
