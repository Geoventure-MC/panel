# CLAUDE.md — Écosystème Geoventure-MC (Panel · Launcher · Installer)

> Fichier de mémoire pour Claude Code. À placer à la racine du repo `panel`
> (et idéalement une copie dans `installer` et `launcher`).
> Dernière mise à jour : 2026-06-28.

## 🎯 Vue d'ensemble

Trois dépôts qui **travaillent ensemble** :

| Repo | Stack | Rôle |
|------|-------|------|
| `geoventure-mc/panel` | **Laravel 11 + PHP 8.2** (Blade, Bootstrap) | Panel d'admin web : crée les users, gère serveurs/mods/loader/whitelist/RPC/UI, **expose la config au launcher** via `/utils/*` et `/data` |
| `geoventure-mc/launcher` | **Electron 37 + JS vanilla** | App de jeu. Lit la config du panel. `env: "panel"` (NE JAMAIS CHANGER). `settings: https://launcher.geoventure.fr/` |
| `geoventure-mc/installer` | **Vue 3 + TS + Vite + PHP** | Installe le panel sur le serveur web (télécharge `panel-*.zip` depuis `CentralCorp/centralpanel-v2`) |

3 serveurs gérés : **Geoventure** (#4ade80), **Elandor** (#a78bfa), **Pokeland** (#fb923c). Forge 1.20.1-47.4.20.

## 🔗 Contrat Panel ↔ Launcher (CLÉ)

Le launcher lit le panel via ces routes (définies dans `panel/routes/web.php`) :
- `GET /utils/api`  → `api/ApiController@getOptions` : toute la config (maintenance, loader, serveur, RPC, UI, whitelist…)
- `GET /utils/mods` → `api/ModController@getMods` : mods optionnels
- `GET /utils/notifications` → `api/NotificationController@getNotifications` : annonces in-app
- `GET /utils/servers-status` → `api/ServerStatusController@getServersStatus` : statut en ligne des serveurs (SLP, cache 30s)
- `GET /utils/community-mods` → `api/CommunityModController@getCommunityMods` : mods communauté approuvés
- `GET /utils/leaderboards` → `api/LeaderboardController@getLeaderboards` : classement joueurs (lit la **DB Azuriom externe**, connexion `azuriom`, cache 60s) — **implémenté** ✅. Envoie `ETag` + `Cache-Control: max-age=30`, renvoie `304` sur `If-None-Match` (polling-friendly, pas de SSE).
- `GET /utils/factions` → `api/FactionController@getFactions` : liste des factions (lit la **DB GeoFactions externe**, connexion `game`, cache 60s) — **implémenté** ✅. Idem `ETag` + `Cache-Control: max-age=30` + `304` sur `If-None-Match`.
- `GET /utils/achievements` → `api/AchievementController@getAchievements` : catalogue des succès (`code`, `name`, `description`, `icon`, `points`, `rarity`, `category`, `condition_type`, `condition_value`). `condition_type ∈ first_launch|launch_count|playtime_hours|instances_tried|manual`. `rarity ∈ common|uncommon|rare|epic|legendary` (ajouté 2026-09-27, migration `2026_09_27_120000_add_rarity_to_achievements_table`, idempotente ; même table que le plugin `AchievementService` et le mod `GeoRarity` ; ⚠ `php artisan migrate` après merge).
- `GET /utils/seasons` → `api/SeasonController@index` : saison en cours + hall of fame (`{ current, past }`). `current` inclut `standings` : top 10 factions `[{name, points}]` lu dans la **DB GeoFactions externe** (`gf_season_points` × `gf_factions`, connexion `game`, config `geoventure.season_standings`, binding sur `external_id`, cache 60s, fail-safe `[]`).
- `GET /utils/wonder` → `api/WonderController@index` : concours de construction Wonder (`{ current, past }`, voir « Wonder » plus bas). ETag + `Cache-Control: max-age=30` + `304`, cache 30s, fail-safe.
- `POST /utils/telemetry` → `api/TelemetryController@store` : télémétrie launcher (opt-in, IP hashée, CSRF exempté)
- `GET /data` → `api/FileController@getFiles` : liste des fichiers du modpack (hash/size/url)
- `GET /api-schema.json` → version du schéma API (statique `{"schemaVersion":"1.0.0"}`)

Le launcher construit l'URL via `settings_url` (= `pkg.settings` ou `localStorage.geoventure_server_url`) + le chemin. Réponses JSON `JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES`.

## 🧩 Multi-instance (launcher « Nexus » — full multi-tenant)

Le launcher **Nexus** propose plusieurs serveurs/instances (Geoventure, Elandor,
Pokeland) via un sélecteur. Chaque instance a son **propre modpack, sa version
Minecraft, son loader et ses mods**. Le launcher route via le paramètre
**`?instance=<slug>`** sur tous les appels `/utils/api`, `/utils/mods`, `/data`
(et `id` slug dans `/utils/servers-status`). **Sans paramètre → comportement
global historique** (100 % rétrocompatible).

Côté panel :
- Table `options_server` enrichie : `instance_slug` (= l'id envoyé par le launcher,
  ex. `geoventure`), `minecraft_version`, `loader_type`, `loader_build_version`,
  `loader_activation`, `data_folder` (sous-dossier modpack). Tous nullable → si
  vide, fallback sur la config globale (`OptionsLoader`, dossier `data/` racine).
- `OptionsServer::resolveInstance($slug)` : matche `instance_slug`, sinon
  `server_id`, sinon le nom slugifié.
- `ApiController` : surcharge `game_version` + `loader.*` par instance, expose
  `instance` dans la réponse. `servers[].id` = `instance_slug ?: server_id`.
- `FileController` : sert `storage/app/public/data/<data_folder|slug>/` quand
  `?instance` est fourni (URLs `storage/data/<slug>/...`), anti path-traversal.
  Fallback `data/` racine si le sous-dossier n'existe pas.
- `ModController` : renvoie les mods `instance = <slug>` **+** les mods partagés
  (`instance IS NULL`).
- `mods.instance` (nullable) : un mod peut cibler une instance ou être partagé.
- Admin → Serveur : champs slug + loader + dossier par instance. Admin → Mods :
  sélecteur d'instance par mod.
- ⚠️ Après merge : `php artisan migrate`. Uploader chaque modpack dans
  `storage/app/public/data/<slug>/` (ex. `data/geoventure/`, `data/elandor/`…).

## ✅ Feature LIVRÉE : Annonces / Notifications

Page admin **📢 Annonces** qui alimente le bandeau de notifications du launcher.

**Panel** (appliqué via zip sur la branche — à committer/migrer) :
- `database/migrations/2026_05_29_120000_create_options_notifications_table.php` — table `options_notifications` (id, type, message, url, active, expires_at, timestamps)
- `app/Models/OptionsNotification.php`
- `app/Http/Controllers/AdminNotificationController.php` — index/store/toggle/destroy
- `app/Http/Controllers/api/NotificationController.php` — `getNotifications()` (actives + non expirées)
- `resources/views/admin/notifications.blade.php`
- `routes/web.php` — routes `admin.notifications.*` + `GET /utils/notifications`
- `resources/views/layouts/admin.blade.php` — entrée sidebar (icône `bi-megaphone`)
- `lang/fr/messages.php` + `lang/en/messages.php` — bloc `notifications.*`, `sidebar.notifications`, `flash.notification_*`
- ⚠️ Après merge : `php artisan migrate`

**Launcher** (déjà poussé sur `master`) :
- `src/assets/js/panels/home.js` → `initNotifications()` lit `{settings_url}utils/notifications` (et `refreshAllServersStatus` lit `{settings_url}utils/servers-status`).
- Bandeau déjà en place : `#notifications-banner` dans `src/panels/home.html`, styles + i18n (`notif_learn_more`).
- Format attendu par le launcher : `[{ id, type, message, url, expiresAt, createdAt }]`, `type ∈ info|warning|maintenance|event`.

## 🩹 Stabilité / correctifs réseau (session 2026-06-09)

Correctifs livrés suite aux erreurs d'une session launcher live (502/404/double-slash/JSON-parse).

**Launcher** (poussé sur `master`) :
- `utils/config.js` — `getAzAuthUrl()` garde-fou si `azauth` null ; `GetConfig()` vérifie `response.ok` (sinon throw) ; `GetNews()` non-fatal (renvoie un placeholder au lieu de throw).
- `launcher.js` + `panels/login.js` — `getAzAuthUrl()` garde-fou null ; suppression du `console.log('initPreviewSkin called')` (debug) ; null-guards sur les lookups DB `accounts-selected → accounts` dans `initPreviewSkin()`/`initOthers()` (plus de crash au 1er lancement / compte absent).
- `utils.js` — `getAzAuthUrl()` garde-fou `config.config.azauth` null ; `headplayer(pseudo)` ignore la requête skin si pseudo vide (évite `.../avatars/face//` 404).
- `panels/settings.js` — les 3 fetch de mods (`updateModsConfig`, `createModsConfig`, `displayMods`) vérifient `response.ok` avant `.json()` (évite `SyntaxError: Unexpected token '<'` sur page HTML 502).
- `panels/home.js` — `_doLaunch()` : `await launch.Launch()` dans un `try/catch` ; en cas d'échec (ex. `GetInfoVersion: Failed to fetch`), le bouton play réapparaît + message `launch_error` au lieu d'une promesse rejetée non gérée + bouton bloqué.
- `index.js` — `os.platform()` (au lieu de `os ==`) ; garde sur `releases_url` avant accès `assets`.
- i18n `launch_error` ajoutée (fr/en).

**Panel** (poussé sur `main`) :
- `api/ApiController.php` — `azauth` jamais null (fallback `azuriom_url` puis `""`) ; defaults loader alignés sur le serveur réel : `game_version` → `1.20.1`, `loader.build` → `1.20.1-47.4.20`, robustes aussi si le champ existe mais est vide (évite un `game_version` vide qui casse `GetInfoVersion`).
- `api/FileController.php` — `GET /data` renvoie `[]` (200) si `storage/app/public/data` absent + garde sur `scandir()` (sinon `foreach(false)` → TypeError → 500 HTML → launcher plante au téléchargement du jeu).
- `AdminServerController.php` + `routes/web.php` — suppression de la route/méthode `server/update` morte et risquée (mass-assignment sur `OptionsServer::first()`).

**Skin API (Azuriom)** : le launcher utilise déjà les bons endpoints du plugin Skin-API (`/api/skin-api/avatars/face/{name}`, `/skin3d/3d-api/skin-api/{name}`, `POST /api/skin-api/skins/update`). Les 404 observés = plugin Skin-API non installé/actif sur le domaine Azuriom, **pas** un bug launcher.

**À faire côté serveur (infra, pas du code)** :
- Uploader le modpack Forge 1.20.1 dans `storage/app/public/data/` du panel.
- Vérifier Admin → Loader (`1.20.1`, forge `1.20.1-47.4.20`, activé) et Admin → Général (`azuriom_url`).
- Installer/activer le plugin Skin-API sur l'Azuriom pour les avatars.

## ✅ Feature LIVRÉE : Pont web → jeu (Commandes jeu)

Page **Admin → Commandes jeu** (`AdminGameCommandController`, vue
`admin/game_commands.blade.php`, routes `admin.game-commands[.store]`,
sidebar `bi-joystick`) : insère des commandes dans la table `gf_web_commands`
de la base du jeu (connexion `game`, `GEO_GAME_DB_*`) que le plugin
GeoFactions consomme toutes les 5 s. Types : `give_coins`, `give_key`,
`season_points`, `bank_deposit`, `broadcast`, `trigger_event`. Historique 50
dernières (pending/done/failed + résultat), audit log, fail-safe si base non
configurée. Contrat complet : `GEOVENTURE-API.md` (repo Pluginmc).

## 📋 Features PANEL à faire

_(backlog vidé — voir « livrées » ci-dessous)_

## ✅ Features PANEL livrées (consolidation 2026-06-18)

- **Mode maintenance enrichi** — toggle rapide 1 clic (`admin.maintenance.toggle`) + message éditable ; le launcher bloque le lancement
- **Journal d'audit** — `AuditLog` + page `admin.audit.index` (paginée, filtres user/action)
- **Rôles & permissions** — `superadmin` / `moderator` (colonne `users.role`, middleware `superadmin`)
- **Sécurité** — contrôle admin (`EnsureUserIsAdmin`), self-update anti zip-slip, import settings restreint, validations uploads, anti mass-assignment, escape `.env`
- **Annonces / Notifications** — table `options_notifications`, admin CRUD, `GET /utils/notifications`
- **Télémétrie & Statistiques** — `POST /utils/telemetry`, page admin stats avec Chart.js
- **Statut serveurs** — `GET /utils/servers-status`, ping SLP Minecraft, cache 30s
- **Leaderboards & Factions** — `GET /utils/leaderboards` + `GET /utils/factions`, lecture réelle des **DB externes** (`config/geoventure.php` : connexion + requête + limite ; connexions `azuriom`/`game` dans `config/database.php`, identifiants `GEO_AZ_DB_*` / `GEO_GAME_DB_*`). Fail-safe : DB non configurée (`database` vide) ou erreur → `[]` (200), cache 60s, jamais de 500
- **Mods communauté** — `GET /utils/community-mods`, admin CRUD, compatible ancien endpoint `api/centralcorp/community-mods`
- **Discord webhooks** — notifications admin critiques via webhook Discord
- **Rate limiting** — 120 req/min sur `/utils/*`, 30 req/min sur telemetry
- **Upload limits** — PHP limits relevées (256M/512M) via `.user.ini` et `.htaccess`
- **Schema API** — `GET /api-schema.json` pour validation de compatibilité launcher
- **Succès / Achievements** — `GET /utils/achievements` (`api/AchievementController@getAchievements`), catalogue serveur (`code`, `name`, `description`, `icon`, `points`, `category`, `condition_type ∈ first_launch|launch_count|playtime_hours|instances_tried|manual`, `condition_value`) fusionné côté launcher avec des compteurs locaux. Leaderboards/Factions servent désormais `ETag` + `Cache-Control: max-age=30` (`304` sur `If-None-Match`) pour le polling 30s du launcher

## ✅ Feature LIVRÉE : Dashboard stats (télémétrie launcher)

Page admin **📊 Statistiques** alimentée par la télémétrie opt-in du launcher.
- `POST /utils/telemetry` → `api/TelemetryController@store` : reçoit `{ event, serverId, launcherVersion, os }` (accepte aussi l'ancien wrapper `{ action:'telemetry', data:{...} }`). IP **hashée** (sha256, pas de PII). Route **exemptée de CSRF** dans `bootstrap/app.php`.
- Table `telemetry_events` (migration `2026_06_09_120000`) + modèle `TelemetryEvent`.
- `Admin\StatsController@index` + `resources/views/admin/stats.blade.php` : lancements/jour (30j), répartition par serveur / version launcher / OS (Chart.js v2.9.4 déjà bundlé dans `admin.js`). Sidebar `bi-bar-chart` + i18n `stats.*`, `sidebar.stats`.
- Launcher : `utils/telemetry.js` poste maintenant sur `{panel}/utils/telemetry` (payload plat). Reste **opt-in** (`localStorage.telemetry_consent`).
- ⚠️ Après merge : `php artisan migrate`.

## ✅ Feature LIVRÉE : `GET /utils/servers-status`

`api/ServerStatusController@getServersStatus` — alimente les pills serveurs du launcher
(`refreshAllServersStatus` dans `panels/home.js`).
- Ping de chaque `OptionsServer` via le **Server List Ping (SLP)** Minecraft moderne
  (handshake + status request) → remonte `online`, `players`, `max_players`, `version`, `latency`.
- Fallback `fsockopen` : si le SLP échoue mais le port répond, `online=true` (joueurs `null`).
- Résultat mis en **cache 30s** par serveur (`server_status_{ip}_{port}`).
- Format renvoyé : `[{ id, name, ip, port, online, players, max_players, version, latency, is_default }]`.
  Le launcher consomme `status.id` (match `data-server-id`), `status.online` et `status.players`.

## 🧩 Conventions PANEL (Laravel) — à respecter

- Contrôleurs admin : `App\Http\Controllers\Admin*` ou `AdminXController`. API : `App\Http\Controllers\api\*`.
- Modèles d'options : `App\Models\Options*` (table `options_*`, `$fillable`, `$casts`).
- Vues : `resources/views/admin/*.blade.php`, `@extends('layouts.admin')`, sections `title`/`page-title`/`content`.
- Flash : `->with('success', __('messages.flash.xxx'))`. Erreurs : `__('messages.common.errors_occurred')`.
- i18n : `lang/fr/messages.php` & `lang/en/messages.php` (tableaux PHP). Apostrophes FR → chaînes en `"..."`.
- Sidebar : `resources/views/layouts/admin.blade.php`, items `bi-*` (Bootstrap Icons).
- Routes admin dans le groupe `Route::prefix('admin')->middleware('auth')` (indentation **4 espaces**).
- Toujours valider `php -l` après modif (PHP dispo dans l'env).

## ⚙️ CI / Release (IMPORTANT)

- **Installer** & **Launcher** : push sur `master` → workflow bump auto la version (`[skip ci]`), build, et crée une **GitHub Release** (avec `installer.zip` / binaires launcher). Release notes user-friendly déjà en place côté installer.
- Installer : le ZIP est **autonome** (chemins `/assets/...` locaux, PAS de CDN). Ne pas réintroduire le double-build CDN (causait écran bleu).
- YAML i18n (installer, `src/locales/*.yml`) : apostrophes FR → **double-quotes** sinon build cassé.

## 🔐 Accès / Limitations connues

- Le panel se télécharge en HTTP direct : `https://github.com/CentralCorp/centralpanel-v2/releases/latest` (public). Dernière base : **v1.0.8** (`panel-1.0.8.zip`).
- Bug connu launcher : `config.js getAzAuthUrl` plante si `azauth`/`authUrl` est `null` côté panel → bien configurer l'auth (Admin → Général → `azuriom_url`). Le health-check de l'installer le détecte.
- **Upload de mods lourds (file-manager)** : `config/file-manager.php` n'impose aucune limite (`maxUploadFileSize => null`), mais PHP par défaut bloque (`upload_max_filesize=2M`, `post_max_size=8M`, `max_file_uploads=20`) → l'envoi de plusieurs `.jar` (ex. 109 MB) échoue. Limites relevées dans `public/.user.ini` (PHP-FPM/CGI) **et** `public/.htaccess` (Apache mod_php) : 256M/512M/200 fichiers. ⚠️ Sous **nginx**, ni l'un ni l'autre ne s'applique → régler côté serveur : `client_max_body_size 512m;` (nginx) + `upload_max_filesize`/`post_max_size`/`max_file_uploads` dans le pool PHP-FPM (`www.conf` ou `php.ini`), puis recharger php-fpm + nginx.

## 🌿 Branches de dev

- Installer & Launcher : `claude/friendly-tesla-7kNM4` (mais le user pousse souvent le launcher direct sur `master`).
- Panel : nouveau repo — créer une branche dédiée (ex: `claude/...`) et ouvrir une **PR draft**.

## Succès : secrets, niveaux, points (2026-10-01, lot 5)

- Migration idempotente `2026_10_01_120000_add_secret_levels_to_achievements_table` : colonnes `secret` (bool) et `max_level` (1 à 5, niveaux I à V) ; `points` (points PAR niveau) et `category` existaient déjà. ⚠ `php artisan migrate`.
- `GET /utils/achievements` ajoute `secret` et `max_level` (rétrocompatible : champs additionnels) ; un succès secret est servi masqué (`name` « ??? », description vide, icône null).
- Admin → Succès : champs Niveaux et Secret. Le catalogue en jeu (65 succès, compteurs) vit dans le plugin (`achievement-catalog.yml`) ; le panel n'a pas à les connaître pour qu'ils fonctionnent en jeu.

## Wonder : concours de construction par équipes (lot 10, 2026-10-05)

Page **Admin → Wonder** (`AdminWonderController`, vue `admin/wonder.blade.php` + `partials/wonder_edition_fields`, sidebar `bi-buildings`, i18n `wonder.*`, journal d'audit `wonder.*`). Tables `wonder_editions` / `wonder_teams` / `wonder_scores` (migration idempotente `2026_10_05_120000`, ⚠ `php artisan migrate`), modèles `WonderEdition` (phase, pondérations normalisées, `ranking()`), `WonderTeam`, `WonderScore`.
- Édition : nom ≤ 40, thème ≤ 80, monde, ouverture/clôture, taille d'équipe (18), bâtisseurs (3), barème technique/esthétique/thème (pondérations normalisées sur 100), récompenses (texte public). Équipes : couleur, bâtisseurs, remplaçants (spectateurs), galerie d'URL https ; un joueur = une équipe par édition. Jury : une note 0-10 par volet et par juré (upsert), moyenne des jurés pondérée → total /100 (égalité : technique, puis nom). « Publier » est irréversible et fige notes et équipes.
- `GET /utils/wonder` (public, throttle 120/min) : `{current, past}` ; `current` = édition ouverte/close, sinon la prochaine, sinon la dernière ; `ranking` seulement une fois publié ; `gallery` = équipes + membres + images. Dates en epoch ms.
- **Contrat avec le plugin** via `gf_web_commands` (séparateur `|`, tout est idempotent) : `wonder_edition` (target = JSON `{id,name,theme,world,open,close,size,builders}`, dates en secondes), `wonder_team` (`id|équipe|#couleur`, amount 1 = créer/MAJ, 0 = supprimer), `wonder_member` (`id|équipe|pseudo`, amount 1 bâtisseur / 2 remplaçant / 0 retirer), `wonder_podium` (`id|1er|2e|3e`), `wonder_cancel` (`id`). Base du jeu absente : les données restent dans le panel, avertissement, bouton « Resynchroniser » pour tout renvoyer. Plugin : `event/WonderManager`.

## Tests E2E (Playwright, 2026-10-07)

`composer install && npm ci && npm run test:e2e` (Chromium : `/opt/pw-browsers/chromium`, ne jamais `playwright install` ; autre chemin via `CHROMIUM_PATH`). Tout est local, aucun workflow GitHub Actions.
- `tests/e2e/global-setup.mjs` : base SQLite jetable (`tests/e2e/.tmp`), `migrate`, admin `e2e-admin@example.test`, deux `php artisan serve` (8765 = base du jeu ABSENTE, 8766 = base du jeu/Azuriom configurée mais INJOIGNABLE). Env surchargé par variables, le `.env` n'est pas lu/modifié ; `storage/installed` créé puis retiré.
- `api.spec.mjs` (endpoints publics : JSON valide, jamais de 500, ETag/304 sur leaderboards/factions/wonder/collecte, `?instance=` hostile, télémétrie), `admin.spec.mjs` (login, 28 pages admin, captures), `flows.spec.mjs` (annonce, succès, Wonder, commande jeu, maintenance, serveur → API publique), `zz-logs.spec.mjs` (aucune `local.ERROR` dans `storage/logs/laravel.log`).
- Captures : `tests/e2e/screenshots/` (gitignorées). Les `throttle:N,1` DOIVENT avoir un 3e paramètre (préfixe) distinct : sans lui tous partagent le même compteur par IP.

## Tableau de bord public des joueurs (`/joueurs`, 2026-10-07)

Page publique (sans auth, Blade, `throttle:60,1,joueurs`) : `GET /joueurs` (alias `/dashboard`, 301) et `GET /joueurs/{pseudo}` (profil, 404 propre si inconnu ou pseudo hors `[A-Za-z0-9_]{1,32}`). `PlayerDashboardController` + `App\Services\PublicDashboard` : appelle EN PROCESSUS les contrôleurs `/utils/*` (leaderboards, factions, seasons, wonder, collecte, achievements, servers-status en cache) et décode leur JSON, chaque lecture fail-safe (vide, jamais de 500). Onglets Classement / Pays / Saison (+ hall of fame) / Wonder (édition, résultats, galerie https seulement) / Collecte (jauge, contributeurs, pays) / Succès (secrets masqués) ; pastilles serveurs rafraîchies par JS via `/utils/servers-status` (30 s). Sans JS : tous les panneaux affichés. Profil : classement, pays (roster des factions), succès débloqués et points (table `achievement_unlocks` du panel) ; pas d'IP ni d'e-mail. Les « épinglés » vivent côté plugin : non affichés.
- Vues `resources/views/dashboard/*`, style `public/assets/css/dashboard.css` (teintes de la palette du jeu, couleurs de rareté), i18n `messages.dashboard.*` fr/en, SEO title/description/OpenGraph, liens depuis `/status` et la sidebar admin.
- Dépend de la base du jeu : Pays (members/power/bank/roster), Saison (standings), Collecte (tout), profil (pays). Dépend d'Azuriom : Classement. Panel seul : succès, Wonder, hall of fame, statuts.
- Tests : `tests/e2e/dashboard.spec.mjs` (6 onglets, base absente et injoignable, 404, mobile, échappement, secrets).


## Analytique : « Geoventure Analytics » (2026-10-08)

Pages **Admin → Analytique** (sidebar `bi-graph-up-arrow`, middleware `admin`) qui lisent la **base du jeu** (connexion `game`, LECTURE SEULE) alimentée par le plugin GeoFactions. Contrat des tables (ne pas le changer côté panel) : `gf_metrics(id, ts BIGINT epoch s, scope, ref '', metric, value DOUBLE, extra NULL)`, `gf_events(id, ts, type, ref, actor NULL, data JSON NULL)`, `gf_rollup` (même forme que `gf_metrics`, moyennes horaires/journalières des données anciennes : le panel interroge `gf_metrics` ET `gf_rollup` et fusionne, la donnée brute prime). Index conseillés côté plugin : `(scope, metric, ref, ts)` et `(type, ts)`.
- **Code** : `App\Services\GameAnalytics` (toutes les lectures, cache 30 s, bornes arrondies à 30 s, requêtes paramétrées via le query builder, jamais de SQL concaténé), `Admin\AnalyticsController` (pages + `GET /admin/analytics/data`), vues `resources/views/admin/analytics/*`, JS `public/assets/js/analytics.js` (Chart.js 4.4.3 VENDORISÉ dans `public/assets/vendor/chart.umd.js` — contrairement à ce que dit la section Statistiques, admin.js ne contient PAS Chart.js —, carte SVG des trajectoires, auto-refresh 30 s), i18n dans **`lang/{fr,en}/analytics.php`** (fichier séparé de `messages.php` pour éviter les conflits ; clés `__('analytics.…')`).
- **Pages** (routes `admin.analytics.*`) : `overview`, `countries` (+ `country/{pays}`), `research`, `oil`, `economy`, `ecology`, `players`, `war` (Guerre & missiles), `usage`, `events` (+ `events.export` CSV). Sélecteur de période 24h/7d/30d/90d/1y (`?period=`), valeur invalide = 7d.
- **Fail-safe** : `GameAnalytics::status()` = `unconfigured` (base vide) | `unreachable` (connexion KO, timeout PDO 3 s) | `no_tables` | `ok` (cache 30 s). Tout état ≠ `ok` rend `admin/analytics/unavailable` (écran explicatif + DDL du contrat), HTTP 200. Une requête en erreur donne un résultat vide (jamais de 500). `config/database.php` : `game.driver` = `GEO_GAME_DB_DRIVER` (défaut `mysql`, `sqlite` pour les tests).
- **Courbes** : `GET /admin/analytics/data?kind=series&period=&series[]=scope|metric|ref|agg` (ref `*` = somme des pays, agg `avg`|`max`), `kind=missiles`, `kind=missile&id=`. Pas d'agrégation choisi par `bucketFor()` (60 s … 1 jour) pour ~400 points max. scope ∈ liste blanche, metric `^[a-z][a-z0-9_]{0,47}$`, ref `^[\p{L}\p{N}_ .:\-']{0,64}$`. Throttle 600/min (admin authentifié, une page = ~10 requêtes).
- **Pas de PII** : `actor` n'est jamais lu ; `GameAnalytics::scrub()` retire du JSON des événements les clés `player, uuid, pseudo, ip, killer, victim, owner, leader…`.
- **Guerre & missiles** : tirs par palier / taux d'interception / blocs détruits calculés depuis `gf_events` (`missile_launched|refused|intercepted|impact|shot_down_native`, plafond 2 500 par type), carte SVG origine→cible filtrable par monde, détail d'un tir (événements liés par `data.missile_id` ou `ref`), pays offensifs/défensifs (`ref` = pays lanceur ; `target_faction`, `defense_faction`, `damage_zone_faction`), compteurs 24 h glissantes `missiles`/`war` par pays. **Usage** : dernier lot du scope `usage` regroupé par préfixe de métrique (`weapon*`, `vehicle|train|convoy*`, `crate|lootbox*`, `bourse*`, `wheel|roue|tombola*`, `arcade*`, `boss*`, `disaster|meltdown|nuclear*`), libellé = `extra` sinon `ref`, clic = courbe en fenêtre.
- **Tests** : `tests/e2e/fixtures/make-game-db.php` fabrique une base SQLite jetable (données relatives à « maintenant », mode `full` ou `empty`) ; `global-setup.mjs` lance 2 serveurs de plus (ports +2 données, +3 sans tables) ; `tests/e2e/analytics.spec.mjs` couvre chaque page avec données, sans tables, base injoignable et non configurée. Captures `tests/e2e/screenshots/analytics-*.png`.
- ⚠ À vérifier avec de vraies données : noms exacts des métriques du scope `usage`, présence de `branch` dans `research_unlocked` (sinon déduite du préfixe de l'id), `ref` des événements `missile_*`, volumétrie de `gf_rollup`/index.
## Supervision du serveur de jeu et alertes (2026-10-08)

Page **Admin → Supervision** (`AdminMonitorController`, vue `admin/monitor.blade.php`, routes `admin.monitor[.update|.test]`, sidebar `bi-activity`, i18n `monitor.*` / `sidebar.monitor` / `flash.monitor_*`), alimentée par `php artisan geo:monitor`.
- ⚠ **Après merge : `php artisan migrate`** (migration idempotente `2026_10_07_120000_create_monitoring_tables` : `server_checks`, `server_incidents`, `options_monitoring`).
- ⚠ **Cron hébergement obligatoire** : `* * * * * cd /chemin/du/panel && php artisan schedule:run >> /dev/null 2>&1`. `routes/console.php` planifie `geo:monitor` chaque minute (`withoutOverlapping`). Sans cron, la page affiche « sonde ancienne ».
- `geo:monitor` → `Services\ServerMonitor::run()` : pour chaque `OptionsServer`, ping SLP via `ServerStatusController::probe()` (public, sans cache ni effet de bord, réutilise `doPing`), insère une ligne `server_checks` (clé = `instance_slug ?: server_id`), évalue les incidents, purge > 30 jours (au plus 1×/h). Code de sortie toujours 0 (fail-safe).
- **Incidents** (`server_incidents`, types `offline` / `latency` / `empty`) : ouverture seulement si TOUTES les sondes de la fenêtre (N minutes) remplissent la condition ET que la fenêtre est couverte (1ʳᵉ sonde ≤ début + 90 s : on n'alerte pas sans historique). **Anti-spam** : un message à l'ouverture, un rappel espacé de `reminder_minutes` (offline/latency uniquement), un message de résolution avec la durée. `empty` (0 joueur, « info ») : un seul message, résolution silencieuse, désactivable (0). Chaque ouverture/résolution écrit le journal d'audit (`monitor.incident`, `monitor.resolved`).
- **Alertes** : `DiscordWebhook::sendEmbed()` (nouveau, timeout court, ne lève jamais). Webhook = `options_monitoring.webhook_url`, sinon `options_general.discord_webhook_url` ; aucun message sans webhook (les incidents sont tout de même enregistrés). Langue fixe `config('geoventure.monitor_alert_locale')` (`GEO_ALERT_LOCALE`, défaut `fr`) car le cron n'a pas de session. L'URL du webhook n'est jamais écrite dans l'audit.
- **Réglages** (`OptionsMonitoring::current()`, défauts si table absente) : activé, hors ligne après 3 min, seuil latence 800 ms pendant 5 min, 0 joueur après 120 min (0 = off), rappel 30 min. Bouton « Envoyer une alerte de test » (throttle 5/min).
- **Disponibilité** 24 h / 7 j / 30 j = part de sondes en ligne (une requête SQL agrégée) ; graphique Chart.js 24 h (joueurs + latence, tranches de 10 min).
- **`GET /utils/uptime`** (`api/UptimeController`, **throttle propre 60/min**, hors du groupe `utils` : `/joueurs` l'appelle à chaque chargement et épuiserait les 120/min partagés) : `{servers:[{id,name,online,uptime24h,uptime7d}]}`, aucune IP/port, ETag + `max-age=30` + 304, cache 30 s, fail-safe `{servers:[]}`. `online` = dernière sonde < 10 min. `/joueurs` : `PublicDashboard::servers()` ajoute `uptime24h`, les pastilles affichent « 99,9 % » (`small.up`, `data-id`).
- Tests : `tests/e2e/monitor.spec.mjs` (faux SLP + faux webhook dans le process de test ; ⚠ les commandes artisan y sont ASYNCHRONES, `spawnSync` bloquerait les faux serveurs). Page `/admin/monitor` ajoutée à `admin.spec.mjs`.
- Piège connu (préexistant) : en suite complète, `flows.spec` « serveur ajouté » peut prendre un 429 (throttle `utils` 120/min partagé par IP) sur machine rapide.

## API MCP : pilotage du panel par Claude (mcp/, 2026-10-08)

Endpoint `POST /api/mcp` (`routes/api.php` => sans session ni CSRF ; `api\McpController`), JSON-RPC 2.0, transport HTTP « streamable » (réponses `application/json`, notifications => 202, GET/DELETE => 405). Méthodes : `initialize` (avec `instructions` en français), `notifications/initialized`, `ping`, `tools/list`, `tools/call`. Erreurs HTTP-niveau avec corps JSON-RPC : −32000 désactivé (404) / −32001 non authentifié (401) / −32002 IP refusée (403) / −32003 débit ou blocage (429) / −32004 corps trop gros (413). Une erreur d'outil est un résultat `isError: true` (jamais une 500).
- **Activation** : DÉSACTIVÉ par défaut. Admin → **API MCP** (super-admin, sidebar `bi-robot`, `lang/*/mcp.php`) : interrupteur global (table `mcp_settings`), création/révocation de clés, journal des appels paginé. Avertissement si la page n'est pas en HTTPS. ⚠ `php artisan migrate` (tables `mcp_keys`, `mcp_calls`, `mcp_settings`).
- **Clés** : `gmcp_` + 40 caractères aléatoires, générée côté serveur, affichée UNE fois à la création (jamais stockée : SHA-256 + préfixe de 8 caractères). Portée `read` < `write` < `admin`, liste d'outils optionnelle, IP/CIDR optionnels, expiration, révocation, dernier usage (date + IP hashée). Comparaison des hash en temps constant.
- **Défenses** (`config/mcp.php`, surchargeables par env `MCP_*`) : 60 req/min par clé, 300/min par IP, blocage 15 min après 8 échecs d'authentification, délai aléatoire sur échec, corps ≤ 64 Ko.
- **Règles des outils** (`app/Services/Mcp/`, `Tools/{Read,Write,Admin}Tools.php`) : schémas JSON stricts (`additionalProperties:false`) + validation Laravel ; `tools/list` ne montre que les outils permis par la portée et `allowed_tools` ; write/admin exigent `confirm:true` ; un outil `destructive` DOIT être `admin` (exception au chargement sinon). Aucun secret en entrée/sortie : `McpSanitizer` masque (••••) les champs sensibles et les clés `gmcp_`/URL de webhook jusque dans les sorties et les journaux. Chaque écriture => `AuditLog` (`mcp.<action>`, `changes.actor = « MCP:<clé> »`, avant/après) + table `mcp_calls` (arguments tronqués sans secrets, statut, durée) ; certaines actions déclenchent aussi le webhook Discord critique. Nouvel outil : une entrée `McpTool::make(...)` dans le bon fichier + un test.
- **Outils** — read : `panel_status`, `stats_overview`, `stats_launches`, `audit_log`, `notifications_list`, `achievements_list`, `wonder_editions`, `wonder_get`, `collect_get`, `community_mods_list`, `mods_list`, `servers_list`, `options_get`, `users_list`, `monitor_status`, `monitor_incidents`, `analytics_overview`, `launcher_config_preview`, `game_commands_history`. write : `notification_create`, `notification_toggle`, `maintenance_set`, `achievement_upsert`, `community_mod_set_status` (active on/off), `wonder_edition_update` (refusé si publiée, renvoie l'édition au jeu), `game_command_send` (types de `AdminGameCommandController::TYPES`, plafonds `mcp.game_command_caps`, motif obligatoire, 30/h/clé), `monitor_test_alert`. admin : `option_set` (liste blanche par section, secrets refusés), `server_instance_upsert`, `user_role_set` (seulement vers `moderator`, jamais d'élévation, jamais le dernier superadmin), `notification_delete`, `notification_purge` (les deux destructifs).
- **Branchement** : `claude mcp add --transport http panel https://<domaine>/api/mcp --header "Authorization: Bearer <clé>"`, ou dans `.mcp.json` : `{"mcpServers":{"panel":{"type":"http","url":"https://<domaine>/api/mcp","headers":{"Authorization":"Bearer ${PANEL_MCP_KEY}"}}}}` (la clé vient de la variable d'environnement `PANEL_MCP_KEY`, jamais du dépôt).
- **Tests** : `tests/e2e/mcp.spec.mjs` (11 tests : désactivé par défaut, clé affichée une fois, filtrage par portée, confirm, secrets, révocation/expiration, débit, blocage, audit).
