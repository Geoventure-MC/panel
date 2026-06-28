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
```

```bash
php artisan config:cache   # si la config est cachée en prod
```

> Sans jeton configuré, `POST /utils/achievements/unlock` renvoie **403** : les
> succès serveur ne se débloquent pas. Idem pour `SEASONS_INGEST_TOKEN` et
> `POST /utils/seasons/sync` (fail-closed). Le plugin GeoFactions doit utiliser
> **le même** `SEASONS_INGEST_TOKEN`.

### Saisons / leaderboards saisonniers

Le plugin GeoFactions signale le cycle de vie des saisons au panel ; le launcher
affiche la saison en cours + un hall of fame des saisons passées.

- `POST /utils/seasons/sync` (ingestion, CSRF exempté, jeton requis) :
  `{ token, action, season:{external_id,name,starts_at,ends_at}, winner?:{name,faction,score,reward} }`.
  `action='start'` ouvre/active la saison, `action='end'` la clôt et enregistre
  le vainqueur. Jamais de 500.
- `GET /utils/seasons` (public) → `{ current: {...}|null, past: [...] }`, dates en
  **epoch millisecondes**.
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
