import { test, expect } from '@playwright/test';
import { execFile } from 'node:child_process';
import http from 'node:http';
import net from 'node:net';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { panelEnv } from './global-setup.mjs';
import { login, shot } from './helpers.mjs';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const env = panelEnv();

// Asynchrone (pas spawnSync) : les faux serveurs webhook/SLP vivent dans ce processus et doivent répondre pendant la commande.
function artisan(...args) {
  return new Promise((resolve, reject) => {
    execFile('php', ['artisan', ...args], { cwd: root, env, encoding: 'utf8' }, (err, stdout, stderr) =>
      err ? reject(new Error(`artisan ${args.join(' ')}:\n${stdout}\n${stderr}`)) : resolve(stdout));
  });
}
const tinker = (code) => artisan('tinker', `--execute=${code}`);
const monitor = () => artisan('geo:monitor');
/** Insère des sondes passées : [minutesAgo, online, players, latency]. */
const seed = (rows) => tinker(rows.map(([m, on, p, l]) =>
  `\\App\\Models\\ServerCheck::create(['server_key'=>'montest','online'=>${on},'players'=>${p},'latency'=>${l},'created_at'=>now()->subMinutes(${m})]);`).join(''));
const setOpts = (o) => tinker(`\\App\\Models\\OptionsMonitoring::current()->update(${o});`);
const dbCount = async (expr) => Number((await tinker(`echo 'N='.(${expr}).PHP_EOL;`)).match(/N=(\d+)/)[1]);

// Faux serveur Minecraft (SLP) : répond au handshake + status request.
const slp = { delay: 0, players: 3, server: null, port: 0 };
function varint(n) { const b = []; do { let t = n & 0x7f; n >>>= 7; if (n) t |= 0x80; b.push(t); } while (n); return Buffer.from(b); }
function startSlp() {
  return new Promise((resolve) => {
    slp.server = net.createServer((sock) => {
      sock.on('error', () => {});
      let got = Buffer.alloc(0), done = false;
      sock.on('data', (d) => {
        got = Buffer.concat([got, d]);
        if (done || got.length < 12) return;
        done = true;
        setTimeout(() => {
          const json = Buffer.from(JSON.stringify({ version: { name: 'Forge 1.20.1', protocol: 763 }, players: { max: 50, online: slp.players, sample: [] }, description: 'test' }));
          const body = Buffer.concat([varint(0), varint(json.length), json]);
          sock.end(Buffer.concat([varint(body.length), body]));
        }, slp.delay);
      });
    }).listen(0, '127.0.0.1', () => { slp.port = slp.server.address().port; resolve(); });
  });
}

// Faux webhook Discord.
const hook = { msgs: [], status: 204, server: null, url: '' };
function startHook() {
  return new Promise((resolve) => {
    hook.server = http.createServer((req, res) => {
      let b = ''; req.on('data', (c) => (b += c));
      req.on('end', () => { try { hook.msgs.push(JSON.parse(b).embeds[0]); } catch {} res.statusCode = hook.status; res.end(); });
    }).listen(0, '127.0.0.1', () => { hook.url = `http://127.0.0.1:${hook.server.address().port}/hook`; resolve(); });
  });
}

// /utils/* est limité à 120 req/min par IP, déjà bien entamé par api.spec : on attend la remise à zéro au besoin.
async function getRetry(request, url, opts) {
  for (let i = 0; i < 3; i++) {
    const r = await request.get(url, opts);
    if (r.status() !== 429) return r;
    await new Promise((res) => setTimeout(res, (Number(r.headers()['retry-after']) || 30) * 1000));
  }
  return request.get(url, opts);
}

test.describe.configure({ mode: 'serial' });

test.describe('supervision', () => {
  test.beforeAll(async () => {
    await startSlp(); await startHook();
    await tinker(`\\App\\Models\\ServerCheck::query()->delete();\\App\\Models\\ServerIncident::query()->delete();\\App\\Models\\OptionsServer::where('instance_slug','montest')->delete();`
      + `\\App\\Models\\OptionsServer::create(['server_id'=>9001,'instance_slug'=>'montest','server_name'=>'Montest','server_ip'=>'127.0.0.1','server_port'=>'1','type'=>'minecraft']);`);
    await setOpts(`['enabled'=>true,'offline_after_minutes'=>3,'latency_threshold_ms'=>100,'latency_after_minutes'=>5,'empty_after_minutes'=>0,'reminder_minutes'=>30,'webhook_url'=>'${hook.url}']`);
  });
  test.afterAll(async () => {
    await tinker(`\\App\\Models\\OptionsServer::where('instance_slug','montest')->delete();\\App\\Models\\ServerCheck::query()->delete();\\App\\Models\\ServerIncident::query()->delete();\\App\\Models\\OptionsMonitoring::current()->update(['webhook_url'=>null]);Cache::forget('utils_uptime_rows');`);
    slp.server.close(); hook.server.close();
  });

  test('hors ligne depuis N minutes : un seul message (anti-spam), rappel espacé', async () => {
    await seed([[5, 0, 'null', 'null'], [4, 0, 'null', 'null'], [3, 0, 'null', 'null'], [2, 0, 'null', 'null'], [1, 0, 'null', 'null']]);
    await monitor();
    expect(await dbCount(`\\App\\Models\\ServerIncident::where('type','offline')->whereNull('resolved_at')->count()`)).toBe(1);
    expect(hook.msgs).toHaveLength(1);
    expect(hook.msgs[0].title).toMatch(/Montest/);
    expect(hook.msgs[0].title).toMatch(/hors ligne/);
    await monitor(); await monitor();
    expect(hook.msgs).toHaveLength(1); // pas de doublon
    expect(await dbCount(`\\App\\Models\\AuditLog::where('action','monitor.incident')->count()`)).toBe(1);

    await tinker(`\\App\\Models\\ServerIncident::query()->update(['last_notified_at'=>now()->subMinutes(31)]);`);
    await monitor();
    expect(hook.msgs).toHaveLength(2);
    expect(hook.msgs[1].title).toMatch(/toujours/);
    await monitor();
    expect(hook.msgs).toHaveLength(2);
  });

  test('retour en ligne : message de résolution avec durée + audit', async () => {
    await tinker(`\\App\\Models\\ServerIncident::query()->update(['started_at'=>now()->subMinutes(75)]);\\App\\Models\\OptionsServer::where('instance_slug','montest')->update(['server_port'=>'${slp.port}']);`);
    slp.players = 4; slp.delay = 0;
    await monitor();
    expect(hook.msgs).toHaveLength(3);
    expect(hook.msgs[2].title).toMatch(/de retour/);
    expect(hook.msgs[2].description).toMatch(/1 h 15 min/);
    expect(await dbCount(`\\App\\Models\\ServerIncident::where('type','offline')->whereNotNull('resolved_at')->count()`)).toBe(1);
    expect(await dbCount(`\\App\\Models\\AuditLog::where('action','monitor.resolved')->count()`)).toBe(1);
  });

  test('latence anormale puis retour à la normale', async () => {
    await tinker(`\\App\\Models\\ServerCheck::where('server_key','montest')->delete();`);
    await seed([[6, 1, 3, 500], [4, 1, 3, 500], [3, 1, 3, 500], [1, 1, 3, 500]]);
    slp.delay = 250;
    await monitor();
    expect(hook.msgs).toHaveLength(4);
    expect(hook.msgs[3].title).toMatch(/Latence/);
    await monitor();
    expect(hook.msgs).toHaveLength(4);
    slp.delay = 0;
    await monitor();
    expect(hook.msgs).toHaveLength(5);
    expect(hook.msgs[4].title).toMatch(/normale/);
  });

  test('0 joueur depuis longtemps : info unique, résolution silencieuse', async () => {
    await setOpts(`['empty_after_minutes'=>30]`);
    await tinker(`\\App\\Models\\ServerCheck::where('server_key','montest')->delete();`);
    await seed([[29, 1, 0, 20], [20, 1, 0, 20], [10, 1, 0, 20], [2, 1, 0, 20]]);
    slp.players = 0;
    await monitor(); await monitor();
    expect(hook.msgs).toHaveLength(6);
    expect(hook.msgs[5].title).toMatch(/Aucun joueur/);
    slp.players = 2;
    await monitor();
    expect(hook.msgs).toHaveLength(6);
    expect(await dbCount(`\\App\\Models\\ServerIncident::where('type','empty')->whereNull('resolved_at')->count()`)).toBe(0);
  });

  test('GET /utils/uptime : JSON minimal, sans IP, ETag/304', async ({ request }) => {
    test.setTimeout(120000);
    await tinker(`Cache::forget('utils_uptime_rows');`);
    const r = await getRetry(request, '/utils/uptime');
    expect(r.status()).toBe(200);
    const text = await r.text();
    expect(text).not.toMatch(/127\.0\.0\.1|"ip"|"port"/);
    const j = JSON.parse(text);
    const s = j.servers.find((x) => x.id === 'montest');
    expect(s).toBeTruthy();
    expect(Object.keys(s).sort()).toEqual(['id', 'name', 'online', 'uptime24h', 'uptime7d']);
    expect(s.online).toBe(true);
    expect(s.uptime24h).toBeGreaterThan(0);
    expect(s.uptime24h).toBeLessThanOrEqual(100);
    const etag = r.headers()['etag'];
    expect(etag).toBeTruthy();
    expect((await getRetry(request, '/utils/uptime', { headers: { 'If-None-Match': etag } })).status()).toBe(304);
  });

  test('pastilles de /joueurs : disponibilité 24 h', async ({ page }) => {
    await tinker(`Cache::forget('utils_uptime_rows');`);
    await page.goto('/joueurs');
    const pill = page.locator('#servers .pill[data-id="montest"]');
    await expect(pill).toHaveCount(1);
    await expect(pill.locator('small.up')).toContainText('%');
    await shot(page, 'monitor-joueurs');
  });

  test('page admin Supervision : état, uptime, graphique, incidents', async ({ page }) => {
    const problems = [];
    page.on('pageerror', (e) => problems.push(e.message));
    await login(page);
    const resp = await page.goto('/admin/monitor');
    expect(resp.status()).toBe(200);
    await expect(page.locator('#monitor-servers [data-server="montest"]')).toBeVisible();
    await expect(page.locator('[data-server="montest"] [data-uptime-h24]')).toContainText('%');
    await expect(page.locator('[data-server="montest"] canvas.monitor-chart')).toBeVisible();
    await expect(page.locator('#monitor-incidents tbody tr')).toHaveCount(3);
    await expect(page.locator('#monitor-incidents')).toContainText(/Offline|Hors ligne/);
    await expect(page.locator('.sidebar-link .bi-activity')).toHaveCount(1);
    await shot(page, 'admin-monitor');
    expect(problems).toEqual([]);
  });

  test('réglages : validation puis enregistrement', async ({ page }) => {
    await login(page);
    await page.goto('/admin/monitor');
    await page.locator('#mon-offline_after_minutes').evaluate((e) => e.removeAttribute('max'));
    await page.locator('#mon-offline_after_minutes').fill('9999');
    await page.locator('#monitor-settings button[type=submit]').click();
    await expect(page.locator('.alert-danger')).toBeVisible();
    await page.locator('#mon-offline_after_minutes').fill('4');
    await page.locator('#mon-reminder_minutes').fill('45');
    await page.locator('#monitor-settings button[type=submit]').click();
    await expect(page.locator('.alert-success')).toBeVisible();
    expect(await dbCount(`\\App\\Models\\OptionsMonitoring::first()->offline_after_minutes`)).toBe(4);
    expect(await dbCount(`\\App\\Models\\OptionsMonitoring::first()->reminder_minutes`)).toBe(45);
    // L'URL du webhook ne doit jamais atterrir dans le journal d'audit.
    expect(await dbCount(`\\App\\Models\\AuditLog::where('action','monitor.settings')->where('changes','like','%127.0.0.1%')->count()`)).toBe(0);
  });

  test('alerte de test : webhook factice, puis échec propre', async ({ page }) => {
    await login(page);
    await page.goto('/admin/monitor');
    const before = hook.msgs.length;
    await page.locator('#monitor-test').click();
    await expect(page.locator('.alert-success')).toBeVisible();
    expect(hook.msgs).toHaveLength(before + 1);
    expect(hook.msgs[before].title).toMatch(/test/i);

    hook.status = 500;
    await page.locator('#monitor-test').click();
    await expect(page.locator('.alert-danger')).toBeVisible();
    hook.status = 204;

    await setOpts(`['webhook_url'=>null]`);
    await tinker(`\\App\\Models\\OptionsGeneral::query()->update(['discord_webhook_url'=>null]);`);
    await page.locator('#monitor-test').click();
    await expect(page.locator('.alert-danger')).toBeVisible();
    await shot(page, 'admin-monitor-test-echec');
  });

  test('supervision désactivée : aucune sonde', async () => {
    await setOpts(`['enabled'=>false]`);
    const n = await dbCount(`\\App\\Models\\ServerCheck::count()`);
    await monitor();
    expect(await dbCount(`\\App\\Models\\ServerCheck::count()`)).toBe(n);
    await setOpts(`['enabled'=>true]`);
  });

  test('endpoints toujours en 200 (fail-safe)', async ({ request }) => {
    expect((await getRetry(request, '/utils/uptime')).status()).toBe(200);
    expect((await request.get('/admin/monitor', { maxRedirects: 0 })).status()).toBeLessThan(400); // redirige vers le login
  });
});
