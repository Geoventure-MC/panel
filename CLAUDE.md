# Panel Geoventure

Panel d'administration web (Laravel 11, PHP 8.2, Blade, Bootstrap). Il configure le launcher Nexus (`Launcher`) et pilote les serveurs de jeu. Forge 1.20.1.

## Règles

- **100 % en français** (commits, releases, PR) : `Ajout :`, `Correction :`, `Amélioration :`, `Documentation :`, `Maintenance :`.
- Branche unique `main`, le hook `post-commit` pousse tout seul.
- Après une modification PHP : `php -l`. Aucun secret dans le dépôt.

## Contrat avec le launcher : à ne pas casser

Le launcher lit `routes/web.php` : `/utils/api` (config), `/utils/mods`, `/utils/notifications`, `/utils/servers-status`, `/utils/community-mods`, `/utils/leaderboards`, `/utils/factions`, `/utils/achievements`, `/utils/seasons`, `/utils/wonder`, `/utils/telemetry` (POST), `/data` (fichiers du modpack). Contrôleurs dans `App\Http\Controllers\api\*`. Changer une réponse impose de vérifier le launcher. Détail : `docs/claude/reference.md`, « Contrat Panel ↔ Launcher ».

## Conventions

- Contrôleurs admin : `App\Http\Controllers\Admin*`. Modèles d'options : `App\Models\Options*` (table `options_*`).
- Vues : `resources/views/admin/*.blade.php`, `@extends('layouts.admin')`, sections `title`, `page-title`, `content`.
- Messages : `->with('success', __('messages.flash.xxx'))`. Traductions : `lang/fr/messages.php`, `lang/en/messages.php` (apostrophes françaises : chaînes en `"..."`).
- Menu : `resources/views/layouts/admin.blade.php`, icônes `bi-*`.
- Routes admin : groupe `Route::prefix('admin')->middleware('auth')`, indentation 4 espaces.

## Avant de toucher X, lire la section de `docs/claude/reference.md`

Trouver le titre avec `grep -n "^## " docs/claude/reference.md`.

| Sujet | Section |
|---|---|
| Multi-instance Nexus, canaux Stable / Test | « Multi-instance », « Système de canaux d'instances » |
| Annonces, notifications | « Annonces / Notifications » |
| Pont web vers le jeu | « Pont web → jeu », « Événements de jeu » |
| Statistiques, analytique, tableau public | « Dashboard stats », « Analytique », « Tableau de bord public » |
| Supervision et alertes | « Supervision du serveur de jeu » |
| API MCP, publication d'un mod | « API MCP », « Publier un mod » |
| Modération | « Modération » |
| Succès, saisons, Wonder | « Succès », « Saisons », « Wonder » |
| Tests E2E Playwright | « Tests E2E » |
| Réseau, accès, limites connues | « Stabilité », « Accès / Limitations » |
