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
- `GET /utils/achievements` → `api/AchievementController@getAchievements` : catalogue des succès (`code`, `name`, `description`, `icon`, `points`, `category`, `condition_type`, `condition_value`). `condition_type ∈ first_launch|launch_count|playtime_hours|instances_tried|manual`.
- `GET /utils/seasons` → `api/SeasonController@index` : saison en cours + hall of fame (`{ current, past }`). `current` inclut `standings` : top 10 factions `[{name, points}]` lu dans la **DB GeoFactions externe** (`gf_season_points` × `gf_factions`, connexion `game`, config `geoventure.season_standings`, binding sur `external_id`, cache 60s, fail-safe `[]`).
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
