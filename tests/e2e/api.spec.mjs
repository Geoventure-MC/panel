import { test, expect, request as pwRequest } from '@playwright/test';
import { PORT_DOWN } from './global-setup.mjs';

// Endpoints JSON lus par le launcher. Jamais de 500, JSON valide, même base du jeu absente OU injoignable.
const JSON_ENDPOINTS = [
  '/utils/api', '/utils/mods', '/utils/notifications', '/utils/achievements', '/utils/wonder', '/utils/collecte',
  '/utils/seasons', '/utils/leaderboards', '/utils/factions', '/utils/servers-status', '/utils/community-mods',
  '/utils/changelog', '/utils/launcher-content', '/utils/scheduled-events', '/utils/servers-history', '/data',
  '/api-schema.json',
];
// Documentés avec ETag + Cache-Control max-age=30 + 304.
const ETAG_ENDPOINTS = ['/utils/leaderboards', '/utils/factions', '/utils/wonder', '/utils/collecte'];

for (const [label, base] of [['base du jeu absente', null], ['base du jeu injoignable', `http://127.0.0.1:${PORT_DOWN}`]]) {
  test.describe(`API publique (${label})`, () => {
    let api;
    test.beforeAll(async ({ playwright }) => {
      api = await playwright.request.newContext({ baseURL: base || process.env.E2E_BASE || `http://127.0.0.1:${process.env.E2E_PORT || 8765}` });
    });
    test.afterAll(async () => { await api.dispose(); });

    for (const p of JSON_ENDPOINTS) {
      test(`GET ${p} -> 200 JSON valide`, async () => {
        const r = await api.get(p);
        const text = await r.text();
        expect(r.status(), text.slice(0, 300)).toBe(200);
        expect(r.headers()['content-type']).toMatch(/json/);
        expect(() => JSON.parse(text)).not.toThrow();
      });
    }

    for (const p of ETAG_ENDPOINTS) {
      test(`ETag/304 sur ${p}`, async () => {
        const r1 = await api.get(p);
        const etag = r1.headers()['etag'];
        expect(etag).toBeTruthy();
        expect(r1.headers()['cache-control']).toMatch(/max-age=30/);
        const r2 = await api.get(p, { headers: { 'If-None-Match': etag } });
        expect(r2.status()).toBe(304);
        const r3 = await api.get(p, { headers: { 'If-None-Match': '"autre"' } });
        expect(r3.status()).toBe(200);
      });
    }

    test('paramètres hostiles ?instance= : jamais de 500', async () => {
      for (const inst of ['../../etc', '..%2f..%2f', "x'--", 'a'.repeat(300), '%00', 'inconnue']) {
        for (const p of ['/utils/api', '/utils/mods', '/data']) {
          const r = await api.get(`${p}?instance=${inst}`);
          expect(r.status(), `${p}?instance=${inst}`).toBeLessThan(500);
        }
      }
    });

    test('POST /utils/telemetry (sans CSRF) accepté, charge invalide refusée proprement', async () => {
      const ok = await api.post('/utils/telemetry', { data: { event: 'launch', serverId: 'geoventure', launcherVersion: '1.0.0', os: 'linux' } });
      expect(ok.status()).toBeLessThan(300);
      const legacy = await api.post('/utils/telemetry', { data: { action: 'telemetry', data: { event: 'launch', serverId: 'x' } } });
      expect(legacy.status()).toBeLessThan(500);
      const bad = await api.post('/utils/telemetry', { data: 'pas du json', headers: { 'content-type': 'application/json' } });
      expect(bad.status()).toBeLessThan(500);
    });
  });
}

test.describe('contrat de forme (launcher)', () => {
  test('/utils/api expose les clés lues par le launcher, azauth jamais null', async ({ request }) => {
    const j = await (await request.get('/utils/api')).json();
    for (const k of ['maintenance', 'maintenance_message', 'loader', 'servers']) expect(j, k).toHaveProperty(k);
    expect(j.azauth ?? '').not.toBeNull();
    expect(typeof j.game_version === 'string' ? j.game_version : j.loader?.minecraft_version ?? '1.20.1').not.toBe('');
  });
  test('/utils/achievements : champs du catalogue', async ({ request }) => {
    const j = await (await request.get('/utils/achievements')).json();
    expect(Array.isArray(j) && j.length > 0).toBeTruthy();
    for (const a of j) for (const k of ['code', 'name', 'points', 'rarity', 'category', 'condition_type', 'secret', 'max_level']) expect(a, `${a.code}.${k}`).toHaveProperty(k);
  });
  test('/utils/wonder et /utils/collecte : forme vide', async ({ request }) => {
    const w = await (await request.get('/utils/wonder')).json();
    expect(w).toHaveProperty('current'); expect(Array.isArray(w.past)).toBeTruthy();
    const c = await (await request.get('/utils/collecte')).json();
    expect(c.active).toBe(false);
  });
  test('/status (page publique) répond 200 HTML', async ({ page }) => {
    const r = await page.goto('/status');
    expect(r.status()).toBe(200);
    await page.screenshot({ path: 'tests/e2e/screenshots/public-status.png', fullPage: true });
  });
});
