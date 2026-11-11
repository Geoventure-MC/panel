# DEPLOYMENT.md — Checklist de déploiement (session succès serveur)

Checklist ops pour mettre en production les nouveautés de cette session :
**succès côté serveur** (GeoFactions → Panel → Launcher), leaderboards live et
navigateur de mods communauté.

## 1. Panel (Laravel)

```bash
php artisan migrate
```

Tables créées / mises à jour côté panel :
- `achievements` (catalogue des succès)
- `achievement_unlocks` (déverrouillages par joueur)
- `seasons` (leaderboards saisonniers : saison en cours + hall of fame)

Les **5 succès serveur** (`faction_member`, `geocoins_1000`, `geocoins_10000`,
`age_iron`, `age_industrial`) sont **auto-seedés** par la migration
`..._seed_server_achievements.php` (idempotente — ré-exécutable sans doublon).

Configurer le jeton d'ingestion dans `.env` (mêmes valeur que côté plugin) :

```env
ACHIEVEMENTS_INGEST_TOKEN=<jeton-secret-partagé>
SEASONS_INGEST_TOKEN=<jeton-saisons-partagé>
EVENTS_INGEST_TOKEN=<jeton-planificateur-partagé>
```

```bash
php artisan config:cache   # si la config est cachée en prod
```

> Sans jeton configuré, `POST /utils/achievements/unlock` renvoie **403** : les
> succès serveur ne se débloquent pas. Idem pour `SEASONS_INGEST_TOKEN` et
> `POST /utils/seasons/sync` (fail-closed). Le plugin GeoFactions doit utiliser
> **le même** `SEASONS_INGEST_TOKEN`.

### Proxy inverse et IP des clients (`TRUSTED_PROXIES`)

Derrière nginx / aaPanel / Cloudflare, `$request->ip()` renvoie l'IP du proxy :
tous les `throttle` par IP partagent alors un seul compteur. Dans `.env` :

```env
TRUSTED_PROXIES=127.0.0.1        # IP/CIDR des proxys, séparés par des virgules
```

- Vide (défaut) : aucun proxy de confiance, comportement inchangé. À laisser
  vide en accès direct.
- Ne pas mettre `*` si le panel est joignable sans passer par le proxy : un
  client pourrait falsifier `X-Forwarded-For`. Derrière Cloudflare, lister les
  plages Cloudflare (ou configurer nginx `real_ip`), pas seulement 127.0.0.1.
- Puis `php artisan config:clear` (ou `config:cache`).
- Effet de bord : les IP enregistrées (audit, télémétrie) deviennent les vraies
  IP clientes ; les visiteurs uniques sont recomptés.

### Télémétrie : `ip_hash` salé

`telemetry_events.ip_hash` est désormais un HMAC-SHA256 de l'IP avec `APP_KEY`
(l'ancien SHA-256 simple se retrouvait par force brute). Les anciennes lignes ne
correspondent plus : les visiteurs uniques sont recomptés une fois à la bascule
(un même joueur compte deux fois sur la période de transition). Changer
`APP_KEY` provoque le même effet.

### Mods communauté : hôtes autorisés

`community_mod_upsert` (MCP) et Admin → Mods Communauté n'acceptent qu'une URL
https dont l'hôte est dans `config/mcp.php` → `community_mod_download_hosts`
(par défaut : github.com, api.github.com, objects.githubusercontent.com,
release-assets.githubusercontent.com, cdn.modrinth.com) et un `filename` du
type `^[A-Za-z0-9._+-]+\.jar$`. Les types `give_coins` et `bank_deposit` de
`game_command_send`, ainsi que `community_mod_delete`, exigent une clé MCP de
portée `admin`. Après déploiement : `php artisan config:clear`.

### Durcissement à vérifier en production

- **Gestionnaire de fichiers** (`/file-manager`) : réservé aux super-admins (un modérateur ne le voit plus). Il écrit
  dans `storage/app/public/data/`, servi publiquement via `/storage/data/` : le serveur web ne doit **jamais** y
  exécuter de PHP. nginx : `location ~* ^/storage/.*\.php$ { return 403; }` placé avant le bloc `\.php$` ;
  Apache : `php_flag engine off` ou `RemoveHandler .php` dans ce dossier.
- **Racine web** : elle doit être `public/` (jamais la racine du projet), sinon `.env`, `storage/logs`, `vendor/` et
  `composer.json` deviennent lisibles. Contrôle : `curl -I https://<domaine>/.env` doit répondre 404/403.
- **Cookies de session** : `SESSION_SECURE_COOKIE=true` dans `.env` dès que le panel est en HTTPS (sinon le cookie n'est
  pas marqué `Secure`).
- **APP_KEY** : la valeur de `.env.example` (et `InstallController::TEMP_KEY`) est publique, c'est la clé TEMPORAIRE de
  l'installeur. La production doit avoir une clé différente (`php artisan key:generate`) ; sinon le secret SSO chiffré,
  les cookies chiffrés et `ip_hash` ne protègent rien. À vérifier : `grep -c hmU1T3 .env` doit afficher 0.
- **Installeur** : `/install` répond désormais 404 dès que la base contient un compte et que la clé n'est plus
  temporaire, même si `storage/installed` a disparu (il lançait `migrate:fresh`).
- **Pont web → jeu** : le plugin doit avoir tourné au moins une fois pour ajouter la colonne `request_id` ; sans elle,
  le panel dépose les commandes sans idempotence (voir `docs/claude/reference.md`).

### Saisons / leaderboards saisonniers

Le plugin GeoFactions signale le cycle de vie des saisons au panel ; le launcher
affiche la saison en cours + un hall of fame des saisons passées.

- `POST /utils/seasons/sync` (ingestion, CSRF exempté, jeton requis) :
  `{ token, action, season:{external_id,name,starts_at,ends_at}, winner?:{name,faction,score,reward} }`.
  `action='start'` ouvre/active la saison, `action='end'` la clôt et enregistre
  le vainqueur. Jamais de 500.
- `GET /utils/seasons` (public) → `{ current: {...}|null, past: [...] }`, dates en
  **epoch millisecondes**.
- `current.standings` (top 10 factions `{name, points}`) est lu dans la DB de jeu
  externe via `GEO_GAME_DB_*` (table `gf_season_points`) — `[]` si non configurée.
- Admin → Saisons : page en lecture seule (nom, statut, dates, vainqueur).

## 2. Plugin (GeoFactions)

`achievements.yml` :
```yaml
enabled: true
panel_url: https://launcher.geoventure.fr
ingest_token: <jeton-secret-partagé>   # IDENTIQUE à ACHIEVEMENTS_INGEST_TOKEN du panel
```

`events.yml` : relire les réglages dynevent (tunables) avant mise en prod.

Table `gf_age_quest_progress` : **auto-créée** par le plugin (rien à faire).

## 3. Launcher

Rien à déployer — release automatique au push. Nouveautés livrées :
- succès in-app (compteurs locaux + catalogue serveur),
- leaderboards live,
- navigateur de mods communauté.

## 4. Boucle des succès serveur

Le plugin détecte la condition en jeu → `POST /utils/achievements/unlock`
`{ player, code, token }` → le panel stocke le déverrouillage dans
`achievement_unlocks` → le launcher lit
`GET /utils/achievements/progress?player=<pseudo>` et affiche le badge
correspondant (fusionné avec le catalogue `/utils/achievements`).

## 5. Vérification

- **Progress endpoint** (doit renvoyer un tableau JSON, `[]` si rien) :
  ```bash
  curl "https://launcher.geoventure.fr/utils/achievements/progress?player=<pseudo>"
  ```
- **Catalogue** (doit lister les 5 succès serveur + les exemples) :
  ```bash
  curl "https://launcher.geoventure.fr/utils/achievements"
  ```
- **Admin → Succès** : les 5 succès serveur apparaissent et sont actifs.
- **Test de déverrouillage** : déclencher une condition en jeu (rejoindre une
  faction, atteindre 1 000 GeoCoins…), puis re-curl le progress endpoint → le
  `code` doit apparaître.
- **Dashboard live** (Admin → Statistiques) : vérifier la remontée d'activité.

### Planificateur d'événements (Admin → Planificateur)

- `php artisan migrate` crée la table `scheduled_events`.
- Définir `EVENTS_INGEST_TOKEN` dans `.env` (même valeur dans le `scheduler.yml`
  du plugin GeoFactions). Sans jeton, `POST /utils/scheduled-events/claim`
  renvoie **403** (fail-closed) et rien ne se déclenche en jeu.
- Le launcher lit `GET /utils/scheduled-events` (public) pour afficher les
  prochains événements ; le plugin réclame les événements dus via
  `POST /utils/scheduled-events/claim` (réclamation atomique, chaque événement
  n'est déclenché qu'une fois ; les récurrents sont re-planifiés).

### Page de statut publique

- `GET /status` — sans authentification, lecture cache uniquement (aucun ping
  bloquant), rafraîchissement client via `/utils/servers-status` toutes les 30 s.
  Partageable telle quelle (Discord, réseaux…).
