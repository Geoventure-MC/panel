# `design/` — palette du panel

`palette.json` est une **copie vendorée** de la palette Geoventure, dont
l'autorité est `design/palette.json` du dépôt `geoMods`.
`public/assets/css/palette.css` en est le produit :
`python3 tools/gen_palette.py`.

Le générateur est le **même fichier** que dans `geoMods` et le `Launcher`, au
caractère près : il détecte ce qui existe autour de lui et n'écrit que les
sorties pertinentes. Il n'y a donc pas de variante par dépôt à maintenir.

## Volontairement réduite

Le panel s'appuie sur un thème d'administration existant (Bootstrap /
SB Admin), dont les gris et le bleu `#4e73df` ne sont **pas** des couleurs
Geoventure. Seules les teintes de marque sont reprises ici — les absorber
toutes reviendrait à repeindre le thème.

## Ce qui reste en dur, et pourquoi

Trois endroits gardent leurs hexadécimaux, après avoir constaté que les
remplacer cassait quelque chose :

| Endroit | Raison |
|---------|--------|
| `admin/stats.blade.php` (JS Chart.js) | Chart.js reçoit des **chaînes JavaScript** ; `var()` n'y résout pas — les graphiques auraient perdu leurs couleurs |
| `admin/server.blade.php` (`placeholder`) | le placeholder montre un **exemple d'hexadécimal à saisir**, pas une couleur appliquée |
| `status.blade.php` | page **autonome**, sans `@extends` : elle ne charge pas `palette.css`, ses variables seraient indéfinies |

Le premier cas se résoudrait en lisant les variables via `getComputedStyle`
au chargement. Ce serait un changement de mécanisme, pas une substitution :
à faire séparément si le besoin se présente.
