// Admin → Modération : sanctions et signalements lus (lecture seule) dans la base du jeu (SQLite jetable branchée sur `game`).
import { test, expect } from '@playwright/test';
import { execFile } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { login, shot, ADMIN } from './helpers.mjs';
import { panelEnv, PORT, PORT_DOWN, PORT_AN_DATA, PORT_AN_EMPTY, PORT_AN_NOCAT } from './global-setup.mjs';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const url = (port) => `http://127.0.0.1:${port}`;
const tinker = (code) => new Promise((res, rej) => execFile('php', ['artisan', 'tinker', `--execute=${code}`], { cwd: root, env: panelEnv(), encoding: 'utf8' }, (e, o, er) => (e ? rej(new Error(o + er)) : res(o))));
const PAGES = ['', '/sanctions', '/reports', '/sanctions/1', '/reports/1', '/players/Alice'];
// Vie privée : ni hash ni adresse ni e-mail, quelle que soit la page.
const noPrivate = (body, where) => {
  expect(body, `${where} : hash d'IP`).not.toMatch(/HASHSECRET/i);
  expect(body, `${where} : adresse IP`).not.toContain('203.0.113');
  expect(body, `${where} : e-mail`).not.toMatch(/@example\.test/);
};
const noCrash = async (page) => expect(await page.locator('body').innerText()).not.toMatch(/Whoops|SQLSTATE|Stack trace|Server Error/);

for (const [label, port] of [['avec données', PORT_AN_DATA], ['sans catalogue (nocat)', PORT_AN_NOCAT]]) {
  test.describe(label, () => {
    test.use({ baseURL: url(port) });
    test.beforeEach(async ({ page }) => { await login(page); });

    test('chaque page répond, sans erreur JS ni donnée privée', async ({ page }) => {
      const errors = [];
      page.on('pageerror', (e) => errors.push(String(e)));
      for (const p of PAGES) {
        const res = await page.goto(`/admin/moderation${p}`);
        expect(res.status(), p).toBe(200);
        await noCrash(page);
        await expect(page.locator('#mod-unavailable')).toHaveCount(0);
        noPrivate(await page.content(), p);
        if (port === PORT_AN_DATA) await shot(page, `moderation-${p.replace(/\//g, '-').replace(/^-/, '') || 'index'}`);
      }
      expect(errors).toEqual([]);
    });
  });
}

test.describe('avec données : contenu', () => {
  test.use({ baseURL: url(PORT_AN_DATA) });
  test.beforeEach(async ({ page }) => { await login(page); });

  test("vue d'ensemble : KPI, graphiques dessinés, signalements ouverts", async ({ page }) => {
    await page.goto('/admin/moderation');
    await expect(page.locator('[data-kpi=active_bans] .v')).toHaveText('1');
    await expect(page.locator('[data-kpi=active_mutes] .v')).toHaveText('1');
    await expect(page.locator('[data-kpi=reports_open] .v')).toHaveText('1');
    await expect(page.locator('[data-kpi=avg_handle] .v')).toContainText('1 h');
    await expect(page.locator('#mod-open-reports tbody tr')).toHaveCount(1);
    await page.waitForFunction(() => window.Chart && [...document.querySelectorAll('canvas[data-mod-chart]')].every((c) => Chart.getChart(c)));
    await expect(page.locator('#mod-staff')).toContainText('ModOne');
    await expect(page.locator('#mod-top')).toContainText('Alice');
  });

  test('sanctions : filtres, recherche, échappement, pas de formule active', async ({ page }) => {
    await page.goto('/admin/moderation/sanctions');
    await expect(page.locator('#mod-sanctions tbody tr')).toHaveCount(7);
    await page.goto('/admin/moderation/sanctions?type=ban');
    await expect(page.locator('#mod-sanctions tbody tr')).toHaveCount(3);
    await page.goto('/admin/moderation/sanctions?state=active');
    await expect(page.locator('#mod-sanctions tbody tr')).toHaveCount(3);
    await page.goto('/admin/moderation/sanctions?state=revoked');
    await expect(page.locator('#mod-sanctions tbody tr')).toHaveCount(1);
    await page.goto('/admin/moderation/sanctions?player=ALI');
    await expect(page.locator('#mod-sanctions tbody tr')).toHaveCount(2);
    await page.goto('/admin/moderation/sanctions?staff=modtwo');
    await expect(page.locator('#mod-sanctions tbody tr')).toHaveCount(3);
    // joker SQL et valeurs invalides : traités comme du texte, jamais d'erreur
    for (const q of ['player=%25', 'player=%27%3B--', 'type=zzz&state=zzz', 'from=zzz&to=2020-13-45', 'page=-4', 'page=99999']) {
      const r = await page.goto(`/admin/moderation/sanctions?${q}`);
      expect(r.status(), q).toBe(200);
      await noCrash(page);
    }
    // le HTML de la raison est échappé (aucun <b> injecté)
    await page.goto('/admin/moderation/sanctions?player=bob');
    await expect(page.locator('#mod-sanctions')).toContainText('<b>gras</b>');
    expect(await page.locator('#mod-sanctions b').count()).toBe(0);
    // les coordonnées privées sont masquées dans la raison
    await page.goto('/admin/moderation/sanctions?player=dave');
    await expect(page.locator('#mod-sanctions')).toContainText('[ip]');
    await expect(page.locator('#mod-sanctions')).toContainText('[e-mail]');
  });

  test('détail de sanction et de signalement : contexte nettoyé', async ({ page }) => {
    await page.goto('/admin/moderation/sanctions/6');
    await expect(page.locator('#mod-sanction-detail')).toContainText('Appel accepté');
    expect((await page.goto('/admin/moderation/sanctions/9999')).status()).toBe(404);
    await page.goto('/admin/moderation/reports/1');
    const ctx = page.locator('#mod-context');
    await expect(ctx).toContainText('[ip]');
    await expect(ctx).toContainText('[e-mail]');
    await expect(ctx).toContainText('<script>alert(1)</script>'); // affiché comme texte
    await expect(ctx).not.toContainText('§');
    expect(await page.locator('#mod-context script').count()).toBe(0);
    expect((await page.goto('/admin/moderation/reports/9999')).status()).toBe(404);
  });

  test('signalements : filtres par statut et cible', async ({ page }) => {
    await page.goto('/admin/moderation/reports');
    await expect(page.locator('#mod-reports tbody tr')).toHaveCount(3);
    await expect(page.locator('#mod-reports tbody tr').first()).toHaveAttribute('data-status', 'open');
    await page.goto('/admin/moderation/reports?status=closed');
    await expect(page.locator('#mod-reports tbody tr')).toHaveCount(1);
    await page.goto('/admin/moderation/reports?target=bo');
    await expect(page.locator('#mod-reports tbody tr')).toHaveCount(1);
  });

  test('recherche de joueur et fiche : comptes liés (super-admin) sans hash', async ({ page }) => {
    await page.goto('/admin/moderation');
    await page.locator('#mod-search input[name=q]').fill('alice');
    await page.locator('#mod-search button').click();
    await expect(page).toHaveURL(/players\/alice/i);
    await expect(page.locator('#mod-player-name')).toHaveText('Alice');
    await expect(page.locator('[data-count=ban]')).toContainText('1');
    await expect(page.locator('#mod-player-active tbody tr')).toHaveCount(1);
    await expect(page.locator('#mod-player-history tbody tr')).toHaveCount(2);
    await expect(page.locator('#mod-player-reports-in tbody tr')).toHaveCount(1);
    await expect(page.locator('#mod-player-reports-out tbody tr')).toHaveCount(1);
    const linked = page.locator('#mod-linked [data-linked]');
    await expect(linked).toHaveCount(2); // Bob et Dave partagent l'adresse, pas Carol
    await expect(page.locator('#mod-linked')).toContainText('Bob');
    await expect(page.locator('#mod-linked')).not.toContainText('Carol');
    noPrivate(await page.content(), 'fiche');
    // inconnu et valeurs hostiles
    await page.goto('/admin/moderation/players/Inconnu');
    await expect(page.locator('#mod-player-unknown')).toBeVisible();
    expect((await page.goto('/admin/moderation/players/<script>')).status()).toBe(404);
    await page.goto('/admin/moderation/search?q=%27%3B--');
    await noCrash(page);
  });

  test('export CSV (super-admin) : séparateur ;, formules neutralisées, rien de privé', async ({ page }) => {
    await page.goto('/admin/moderation/sanctions');
    await expect(page.locator('#mod-export')).toBeVisible();
    const r = await page.request.get('/admin/moderation/export.csv?type=ban');
    expect(r.status()).toBe(200);
    expect(r.headers()['content-type']).toContain('text/csv');
    const txt = await r.text();
    expect(txt).toContain(';');
    expect(txt).toContain("'=HYPERLINK"); // apostrophe devant la formule
    expect(txt).not.toMatch(/\n"?=HYPERLINK/);
    noPrivate(txt, 'csv');
    const all = await (await page.request.get('/admin/moderation/export.csv')).text();
    noPrivate(all, 'csv complet');
    expect(all).toContain('[ip]');
  });

  test("journal d'audit : consultation de fiche et export tracés", async ({ page }) => {
    await page.goto('/admin/moderation/players/Bob');
    await page.request.get('/admin/moderation/export.csv');
    await page.goto('/admin/audit');
    const body = await page.locator('body').innerText();
    expect(body).toContain('moderation.player.view');
    expect(body).toContain('moderation.export');
    expect(body).toContain('moderation.linked.view');
  });
});

test.describe('modérateur (non super-admin)', () => {
  test.use({ baseURL: url(PORT_AN_DATA) });
  test('pas d’export, pas de comptes liés', async ({ page, context }) => {
    await tinker("\\App\\Models\\User::updateOrCreate(['email'=>'mod-e2e@example.test'],['name'=>'Mod E2E','password'=>\\Illuminate\\Support\\Facades\\Hash::make('e2e-Passw0rd!'),'is_admin'=>true,'role'=>'moderator']);");
    await page.goto('/login');
    await page.locator('input[name=email]').fill('mod-e2e@example.test');
    await page.locator('input[name=password]').fill('e2e-Passw0rd!');
    await Promise.all([page.waitForURL(/\/admin/), page.locator('form button[type=submit], form input[type=submit]').first().click()]);
    await page.goto('/admin/moderation/sanctions');
    await expect(page.locator('#mod-sanctions')).toBeVisible();
    await expect(page.locator('#mod-export')).toHaveCount(0);
    expect((await page.request.get('/admin/moderation/export.csv')).status()).toBe(403);
    await page.goto('/admin/moderation/players/Alice');
    await expect(page.locator('#mod-player-name')).toBeVisible();
    await expect(page.locator('#mod-linked')).toHaveCount(0);
    await expect(page.locator('body')).not.toContainText('Bob —');
  });
});

for (const [label, port, state] of [['sans tables', PORT_AN_EMPTY, 'no_tables'], ['base injoignable', PORT_DOWN, 'unreachable'], ['base non configurée', PORT, 'unconfigured']]) {
  test.describe(label, () => {
    test.use({ baseURL: url(port) });
    test('écran explicatif, HTTP 200, DDL du contrat', async ({ page }) => {
      await login(page);
      for (const p of PAGES) {
        const res = await page.goto(`/admin/moderation${p}`);
        expect(res.status(), p).toBe(200);
        await noCrash(page);
        await expect(page.locator('#mod-unavailable')).toHaveAttribute('data-state', state);
      }
      await expect(page.locator('#mod-unavailable')).toContainText('gf_sanctions');
      const csv = await page.request.get('/admin/moderation/export.csv', { maxRedirects: 0 });
      expect([200, 302]).toContain(csv.status());
    });
  });
}
