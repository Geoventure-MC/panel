import { test, expect } from '@playwright/test';
import { execFile } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { panelEnv } from './global-setup.mjs';
import { login, shot } from './helpers.mjs';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const env = panelEnv();

function artisan(...args) {
  return new Promise((resolve, reject) => {
    execFile('php', ['artisan', ...args], { cwd: root, env, encoding: 'utf8' }, (err, stdout, stderr) =>
      err ? reject(new Error(`artisan ${args.join(' ')}:\n${stdout}\n${stderr}`)) : resolve(stdout));
  });
}
const tinker = (code) => artisan('tinker', `--execute=${code}`);
const reset = () => tinker('\\Illuminate\\Support\\Facades\\Cache::flush();');
const setEnabled = (on) => tinker(`\\App\\Models\\McpSetting::put('enabled','${on ? 1 : 0}');`);
/** Crée une clé directement en base (la clé en clair n'existe que dans ce test). */
async function mkKey(name, scope, extra = '') {
  const out = await tinker(`$k=\\App\\Models\\McpKey::generate();\\App\\Models\\McpKey::create(['name'=>'${name}','key_hash'=>\\App\\Models\\McpKey::hash($k),'key_prefix'=>substr($k,0,8),'scope'=>'${scope}'${extra}]);echo 'KEY='.$k.PHP_EOL;`);
  return out.match(/KEY=(gmcp_[A-Za-z0-9]{40})/)[1];
}

let id = 0;
async function rpc(request, key, method, params, headers = {}) {
  const r = await request.post('/api/mcp', {
    headers: { ...(key ? { Authorization: `Bearer ${key}` } : {}), ...headers },
    data: { jsonrpc: '2.0', id: ++id, method, params },
  });
  let body = null;
  try { body = await r.json(); } catch {}
  return { status: r.status(), body };
}
const call = (request, key, name, args = {}) => rpc(request, key, 'tools/call', { name, arguments: args });
const text = (r) => r.body.result.content[0].text;
const names = (r) => r.body.result.tools.map((t) => t.name);

test.describe.configure({ mode: 'serial' });
test.afterEach(async () => { await reset(); });

test('désactivé par défaut : réponse 404 propre', async ({ request }) => {
  const r = await rpc(request, 'gmcp_' + 'a'.repeat(40), 'ping');
  expect(r.status).toBe(404);
  expect(r.body.error.code).toBe(-32000);
});

test('page admin : activation, création de clé affichée une seule fois, révocation', async ({ page, request }) => {
  await login(page);
  await page.goto('/admin/mcp');
  await expect(page.locator('#mcp-https-warning')).toBeVisible(); // test en HTTP
  await page.locator('#mcp-enabled').check();
  await page.locator('form[action$="/mcp/settings"] button[type=submit]').click();
  await expect(page.locator('.alert-success')).toContainText(/activée|enabled/i);

  await page.locator('#mcp-create-form input[name=name]').fill('e2e-ui');
  await page.locator('#mcp-create-form select[name=scope]').selectOption('write');
  await page.locator('#mcp-create-form button[type=submit]').click();
  const key = await page.locator('#mcp-new-key-value').inputValue();
  expect(key).toMatch(/^gmcp_[A-Za-z0-9]{40}$/);
  await shot(page, 'mcp-key-created');

  // Rechargement : plus jamais relisible, seul le préfixe reste.
  await page.goto('/admin/mcp');
  await expect(page.locator('#mcp-new-key-value')).toHaveCount(0);
  expect(await page.content()).not.toContain(key);
  await expect(page.locator('#mcp-keys')).toContainText(key.slice(0, 8));
  // Le hash seul est en base.
  const row = await tinker(`echo json_encode(\\App\\Models\\McpKey::where('name','e2e-ui')->first()->getRawOriginal());`);
  expect(row).not.toContain(key);

  expect((await rpc(request, key, 'ping')).status).toBe(200);
});

test('révocation via la page puis refus 401', async ({ page, request }) => {
  await setEnabled(true);
  const key = await mkKey('e2e-rev', 'read');
  expect((await rpc(request, key, 'ping')).status).toBe(200);
  await login(page);
  await page.goto('/admin/mcp');
  page.once('dialog', (d) => d.accept());
  await page.locator('#mcp-keys tr[data-key-name=e2e-rev] button.btn-outline-danger').click();
  await expect(page.locator('.alert-success')).toContainText(/révoquée|revoked/i);
  const r = await rpc(request, key, 'ping');
  expect(r.status).toBe(401);
  expect(r.body.error.code).toBe(-32001);
});

test('authentification : sans clé, clé inconnue, expirée', async ({ request }) => {
  await setEnabled(true);
  expect((await rpc(request, null, 'ping')).status).toBe(401);
  expect((await rpc(request, 'gmcp_' + 'b'.repeat(40), 'ping')).status).toBe(401);
  const exp = await mkKey('e2e-exp', 'read', ",'expires_at'=>now()->subMinute()");
  expect((await rpc(request, exp, 'ping')).status).toBe(401);
});

test('initialize et tools/list filtrés par portée', async ({ request }) => {
  await setEnabled(true);
  const kr = await mkKey('e2e-r', 'read');
  const kw = await mkKey('e2e-w', 'write');
  const ka = await mkKey('e2e-a', 'admin');
  const kl = await mkKey('e2e-limited', 'admin', ",'allowed_tools'=>['panel_status','notification_create']");

  const init = await rpc(request, kr, 'initialize', { protocolVersion: '2025-03-26', capabilities: {}, clientInfo: { name: 't', version: '1' } });
  expect(init.body.result.serverInfo.name).toBe('geoventure-panel');
  expect(init.body.result.instructions).toContain('Panel');
  const note = await request.post('/api/mcp', { headers: { Authorization: `Bearer ${kr}` }, data: { jsonrpc: '2.0', method: 'notifications/initialized' } });
  expect(note.status()).toBe(202);

  const r = names(await rpc(request, kr, 'tools/list'));
  const w = names(await rpc(request, kw, 'tools/list'));
  const a = names(await rpc(request, ka, 'tools/list'));
  expect(r).toContain('panel_status');
  expect(r).not.toContain('notification_create');
  expect(w).toContain('notification_create');
  expect(w).not.toContain('option_set');
  expect(a).toContain('option_set');
  expect(a).toContain('notification_purge');
  expect(r.length).toBeLessThan(w.length);
  expect(w.length).toBeLessThan(a.length);
  expect(names(await rpc(request, kl, 'tools/list')).sort()).toEqual(['notification_create', 'panel_status']);
  const def = (await rpc(request, kw, 'tools/list')).body.result.tools.find((t) => t.name === 'notification_create');
  expect(def.inputSchema.required).toContain('confirm');
  expect(def.inputSchema.additionalProperties).toBe(false);
});

test('lecture, écriture refusée en read, confirm requis, audit', async ({ request }) => {
  await setEnabled(true);
  const kr = await mkKey('e2e-ro', 'read');
  const kw = await mkKey('e2e-wr', 'write');

  const st = await call(request, kr, 'panel_status');
  expect(st.body.result.isError).toBe(false);
  expect(JSON.parse(text(st)).database.ok).toBe(true);

  const denied = await call(request, kr, 'notification_create', { type: 'info', message: 'x', confirm: true });
  expect(denied.body.result.isError).toBe(true);
  expect(text(denied)).toContain('Portée insuffisante');

  const noConfirm = await call(request, kw, 'notification_create', { type: 'info', message: 'sans confirm' });
  expect(noConfirm.body.result.isError).toBe(true);
  expect(text(noConfirm)).toContain('Confirmation requise');

  const bad = await call(request, kw, 'notification_create', { type: 'nope', message: 'x', confirm: true });
  expect(bad.body.result.isError).toBe(true);
  const unknownArg = await call(request, kw, 'notification_create', { type: 'info', message: 'x', confirm: true, evil: 1 });
  expect(unknownArg.body.result.isError).toBe(true);

  const ok = await call(request, kw, 'notification_create', { type: 'event', message: 'Annonce e2e MCP', confirm: true });
  expect(ok.body.result.isError).toBe(false);
  const created = JSON.parse(text(ok));
  expect(created.active).toBe(true);

  const list = JSON.parse(text(await call(request, kr, 'notifications_list', { active_only: true })));
  expect(list.items.some((n) => n.message === 'Annonce e2e MCP')).toBe(true);

  const tg = await call(request, kw, 'notification_toggle', { id: created.id, confirm: true });
  expect(JSON.parse(text(tg)).active).toBe(false);

  // Audit : acteur MCP:<clé>, et appel présent dans le journal des appels.
  const audit = JSON.parse(text(await call(request, kr, 'audit_log', { source: 'mcp', limit: 10 })));
  expect(audit.items.some((l) => l.actor === 'MCP:e2e-wr' && l.action === 'mcp.notification.create')).toBe(true);
  const calls = await tinker(`echo 'N='.\\App\\Models\\McpCall::where('key_name','e2e-wr')->where('tool','notification_create')->count();`);
  expect(Number(calls.match(/N=(\d+)/)[1])).toBeGreaterThanOrEqual(4);
  const refused = await tinker(`echo 'D='.\\App\\Models\\McpCall::where('key_name','e2e-ro')->where('status','denied')->count();`);
  expect(Number(refused.match(/D=(\d+)/)[1])).toBe(1);
});

test('administration : portée admin, destructifs, élévation interdite', async ({ request }) => {
  await setEnabled(true);
  const kw = await mkKey('e2e-w2', 'write');
  const ka = await mkKey('e2e-a2', 'admin');

  const purgeDenied = await call(request, kw, 'notification_purge', { scope: 'all', confirm: true });
  expect(text(purgeDenied)).toContain('Portée insuffisante');
  const optDenied = await call(request, kw, 'option_set', { section: 'ui', key: 'splash', value: 'x', confirm: true });
  expect(optDenied.body.result.isError).toBe(true);

  await tinker(`\\App\\Models\\OptionsUI::count() || \\App\\Models\\OptionsUI::create(['alert_activation'=>1,'alert_scroll'=>0,'alert_msg'=>'a','video_activation'=>0,'video_url'=>'https://x.test','splash'=>'s','splash_author'=>'a','accent_color'=>'#FFA500']);`);
  const set = await call(request, ka, 'option_set', { section: 'ui', key: 'splash', value: 'Splash MCP', confirm: true });
  expect(set.body.result.isError).toBe(false);
  const got = JSON.parse(text(await call(request, ka, 'options_get', { section: 'ui' })));
  expect(got.values.splash).toBe('Splash MCP');
  const badColor = await call(request, ka, 'option_set', { section: 'ui', key: 'accent_color', value: 'rouge', confirm: true });
  expect(badColor.body.result.isError).toBe(true);

  const elev = await call(request, ka, 'user_role_set', { user_id: 1, role: 'superadmin', confirm: true });
  expect(elev.body.result.isError).toBe(true);
  expect(text(elev)).toContain('interdite');
  const lastSuper = await call(request, ka, 'user_role_set', { user_id: 1, role: 'moderator', confirm: true });
  expect(text(lastSuper)).toContain('dernier superadmin');

  const gc = await call(request, kw, 'game_command_send', { type: 'give_coins', target: 'Steve', amount: 10, reason: 'test e2e', confirm: true });
  expect(gc.body.result.isError).toBe(true); // base du jeu non configurée sur ce serveur
  const gcCap = await call(request, kw, 'game_command_send', { type: 'give_coins', target: 'Steve', amount: 99999999, reason: 'test e2e', confirm: true });
  expect(text(gcCap)).toContain('plafond');
  const gcType = await call(request, kw, 'game_command_send', { type: 'rm_rf', target: 'x', amount: 1, reason: 'test e2e', confirm: true });
  expect(gcType.body.result.isError).toBe(true);

  await call(request, kw, 'notification_create', { type: 'info', message: 'à purger', confirm: true });
  const purge = await call(request, ka, 'notification_purge', { scope: 'all', confirm: true });
  expect(JSON.parse(text(purge)).deleted).toBeGreaterThanOrEqual(1);
});

test('masquage des secrets', async ({ request }) => {
  await setEnabled(true);
  const ka = await mkKey('e2e-sec', 'admin');
  await tinker(`$o=\\App\\Models\\OptionsGeneral::first() ?: \\App\\Models\\OptionsGeneral::create(['mods_enabled'=>1,'file_verification'=>1,'embedded_java'=>0,'game_folder_name'=>'x']);$o->forceFill(['azuriom_api_key'=>'SECRET-AZ-KEY-123','discord_webhook_url'=>'https://discord.com/api/webhooks/1/SECRETHOOK'])->save();`);
  const g = await call(request, ka, 'options_get', { section: 'general' });
  const t = text(g);
  expect(t).not.toContain('SECRET-AZ-KEY-123');
  expect(t).not.toContain('SECRETHOOK');
  expect(JSON.parse(t).values.azuriom_api_key).toBe('••••');

  for (const key of ['azuriom_api_key', 'discord_webhook_url', 'sso_client_id']) {
    const r = await call(request, ka, 'option_set', { section: 'general', key, value: 'https://x.test/y', confirm: true });
    expect(r.body.result.isError).toBe(true);
  }
  const users = text(await call(request, ka, 'users_list'));
  expect(users).not.toContain('example.test'); // aucun e-mail
  expect(users).not.toContain('$2y$');           // aucun hash
  const cfg = text(await call(request, ka, 'launcher_config_preview'));
  expect(cfg).not.toContain('SECRET');
  // La clé MCP n'apparaît jamais dans le journal des appels ni l'audit.
  const logs = await tinker(`echo json_encode([\\App\\Models\\McpCall::all()->toArray(),\\App\\Models\\AuditLog::all()->toArray()]);`);
  expect(logs).not.toContain(ka);
  expect(logs).not.toContain('SECRET-AZ');
});

test('limite de débit par clé', async ({ request }) => {
  await setEnabled(true);
  const key = await mkKey('e2e-rate', 'read');
  let limited = null;
  for (let i = 0; i < 70 && !limited; i++) {
    const r = await rpc(request, key, 'ping');
    if (r.status === 429) limited = r;
  }
  expect(limited).not.toBeNull();
  expect(limited.body.error.code).toBe(-32003);
});

test('blocage après échecs d\'authentification et corps trop gros', async ({ request }) => {
  await setEnabled(true);
  let last = 0;
  for (let i = 0; i < 10; i++) {
    last = (await rpc(request, 'gmcp_' + 'c'.repeat(40), 'ping')).status;
    if (last === 429) break;
  }
  expect(last).toBe(429);
  await reset();
  const key = await mkKey('e2e-big', 'read');
  const big = await request.post('/api/mcp', { headers: { Authorization: `Bearer ${key}` }, data: { jsonrpc: '2.0', id: 1, method: 'ping', params: { pad: 'x'.repeat(70000) } } });
  expect(big.status()).toBe(413);
  const get = await request.get('/api/mcp');
  expect(get.status()).toBe(405);
});

test('journal des appels visible dans la page admin', async ({ page }) => {
  await login(page);
  await page.goto('/admin/mcp');
  await expect(page.locator('#mcp-calls')).toContainText('notification_create');
  await shot(page, 'mcp-page');
  await page.goto('/admin/audit');
  await expect(page.locator('body')).toContainText('MCP:e2e-wr');
  await setEnabled(false);
});
