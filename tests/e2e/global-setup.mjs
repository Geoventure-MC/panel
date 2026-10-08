// Prépare une base SQLite jetable, migre, crée un admin et lance `php artisan serve`.
import { spawn, spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(here, '..', '..');
const tmp = path.join(here, '.tmp');
export const PORT = process.env.E2E_PORT || '8765';
export const ADMIN = { email: 'e2e-admin@example.test', password: 'e2e-Passw0rd!', name: 'E2E Admin' };

export const PORT_DOWN = String(Number(PORT) + 1);
// Analytics : base du jeu SQLite jetable avec données factices / sans tables d'analyse.
export const PORT_AN_DATA = String(Number(PORT) + 2);
export const PORT_AN_EMPTY = String(Number(PORT) + 3);

export function panelEnv(extra = {}) {
  return {
    ...process.env,
    APP_ENV: 'local',
    APP_DEBUG: 'false',
    APP_KEY: 'base64:hmU1T3OuvHdi5t1wULI8Xp7geI+JIWGog9pBCNxslY8=',
    APP_URL: `http://127.0.0.1:${PORT}`,
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: path.join(tmp, 'db.sqlite'),
    CACHE_STORE: 'file',
    SESSION_DRIVER: 'file',
    QUEUE_CONNECTION: 'sync',
    LOG_CHANNEL: 'single',
    // Base du jeu / Azuriom volontairement absentes : les endpoints doivent rester en 200.
    GEO_GAME_DB_DATABASE: '',
    GEO_AZ_DB_DATABASE: '',
    PHP_CLI_SERVER_WORKERS: '4',
    ...extra,
  };
}

function artisan(args, env) {
  const r = spawnSync('php', ['artisan', ...args], { cwd: root, env, encoding: 'utf8' });
  if (r.status !== 0) throw new Error(`artisan ${args.join(' ')} a échoué:\n${r.stdout}\n${r.stderr}`);
  return r.stdout;
}

export default async function globalSetup() {
  fs.rmSync(tmp, { recursive: true, force: true });
  fs.mkdirSync(tmp, { recursive: true });
  fs.writeFileSync(path.join(tmp, 'db.sqlite'), '');
  const env = panelEnv();

  // Caches fichiers d'une exécution précédente (ETag, throttle, statuts).
  for (const d of ['storage/framework/cache/data']) fs.rmSync(path.join(root, d), { recursive: true, force: true });
  fs.mkdirSync(path.join(root, 'storage/framework/cache'), { recursive: true });
  fs.mkdirSync(path.join(root, 'storage/framework/sessions'), { recursive: true });
  fs.mkdirSync(path.join(root, 'storage/framework/views'), { recursive: true });
  fs.mkdirSync(path.join(root, 'storage/logs'), { recursive: true });
  fs.writeFileSync(path.join(root, 'storage/logs/laravel.log'), '');
  // Le panel redirige vers /install tant que ce marqueur est absent.
  const marker = path.join(root, 'storage/installed');
  const hadMarker = fs.existsSync(marker);
  if (!hadMarker) fs.writeFileSync(marker, 'e2e');

  artisan(['migrate', '--force'], env);
  const seed = `\\App\\Models\\User::create(['name'=>'${ADMIN.name}','email'=>'${ADMIN.email}','password'=>\\Illuminate\\Support\\Facades\\Hash::make('${ADMIN.password}'),'is_admin'=>true,'role'=>'superadmin']);`;
  artisan(['tinker', `--execute=${seed}`], env);

  const pids = [];
  const start = (port, extra, label) => {
    const srv = spawn('php', ['artisan', 'serve', '--host=127.0.0.1', `--port=${port}`, '--no-reload'], {
      cwd: root, env: panelEnv({ APP_URL: `http://127.0.0.1:${port}`, ...extra }), stdio: ['ignore', 'pipe', 'pipe'], detached: true,
    });
    const out = fs.createWriteStream(path.join(tmp, `serve-${label}.log`));
    srv.stdout.pipe(out); srv.stderr.pipe(out);
    pids.push(srv.pid);
  };
  start(PORT, {}, 'main');
  // Second serveur : bases du jeu / Azuriom configurées mais INJOIGNABLES (port fermé).
  const dead = { CACHE_PREFIX: 'down_', GEO_GAME_DB_DATABASE: 'geo', GEO_GAME_DB_HOST: '127.0.0.1', GEO_GAME_DB_PORT: '1', GEO_GAME_DB_USERNAME: 'x',
                 GEO_AZ_DB_DATABASE: 'az', GEO_AZ_DB_HOST: '127.0.0.1', GEO_AZ_DB_PORT: '1', GEO_AZ_DB_USERNAME: 'x' };
  start(PORT_DOWN, dead, 'down');
  // Geoventure Analytics : deux serveurs dont la connexion `game` est une base SQLite jetable.
  const fx = path.join(here, 'fixtures', 'make-game-db.php');
  for (const [mode, port, label] of [['full', PORT_AN_DATA, 'andata'], ['empty', PORT_AN_EMPTY, 'anempty']]) {
    const file = path.join(tmp, `game-${mode}.sqlite`);
    const r = spawnSync('php', [fx, file, mode], { encoding: 'utf8' });
    if (r.status !== 0) throw new Error(`fixture analytics ${mode}: ${r.stderr}`);
    start(port, { CACHE_PREFIX: `${label}_`, GEO_GAME_DB_DRIVER: 'sqlite', GEO_GAME_DB_DATABASE: file }, label);
  }
  fs.writeFileSync(path.join(tmp, 'state.json'), JSON.stringify({ pids, markerCreated: !hadMarker }));

  for (const port of [PORT, PORT_DOWN, PORT_AN_DATA, PORT_AN_EMPTY]) {
    let ok = false;
    for (let i = 0; i < 100 && !ok; i++) {
      try { ok = (await fetch(`http://127.0.0.1:${port}/api-schema.json`)).ok; } catch {}
      if (!ok) await new Promise(r => setTimeout(r, 300));
    }
    if (!ok) throw new Error(`php artisan serve ne répond pas sur ${port}`);
  }
}
