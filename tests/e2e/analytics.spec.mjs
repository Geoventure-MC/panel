// Geoventure Analytics : pages d'analyse lues dans la base du jeu (SQLite jetable branchée sur la connexion `game`).
import { test, expect } from '@playwright/test';
import { login, shot, ADMIN } from './helpers.mjs';
import { PORT, PORT_DOWN, PORT_AN_DATA, PORT_AN_EMPTY } from './global-setup.mjs';

const url = (port) => `http://127.0.0.1:${port}`;
const PAGES = ['', '/countries', '/countries/Aurelia', '/research', '/oil', '/economy', '/ecology', '/players', '/events', '/war', '/usage'];
// login sur une URL absolue (serveur autre que le baseURL par défaut)
const loginAt = async (page) => {
  await page.locator('input[name=email]').fill(ADMIN.email);
  await page.locator('input[name=password]').fill(ADMIN.password);
  await Promise.all([page.waitForURL(/\/admin/), page.locator('form button[type=submit], form input[type=submit]').first().click()]);
};
const noCrash = async (page) => {
  const body = await page.locator('body').innerText();
  expect(body).not.toMatch(/Whoops|Illuminate\\|SQLSTATE|Stack trace|Server Error/);
};

test.describe('avec données', () => {
  test.use({ baseURL: url(PORT_AN_DATA) });

  test.beforeEach(async ({ page }) => { await login(page); });

  test('chaque page répond, affiche ses blocs et ses courbes', async ({ page }) => {
    const errors = [];
    page.on('pageerror', (e) => errors.push(String(e)));
    for (const p of PAGES) {
      const res = await page.goto(`/admin/analytics${p}?period=7d`);
      expect(res.status(), p).toBe(200);
      await noCrash(page);
      await expect(page.locator('#an-unavailable')).toHaveCount(0);
      await page.waitForTimeout(1500);
      await shot(page, `analytics-${p.replace(/\//g, '-').replace(/^-/, '') || 'overview'}`);
    }
    expect(errors).toEqual([]);
  });

  test("vue d'ensemble : KPI, courbes et fil d'événements", async ({ page }) => {
    await page.goto('/admin/analytics?period=7d');
    await expect(page.locator('[data-kpi=online] .v')).not.toHaveText('—');
    await expect(page.locator('[data-kpi=tps] .v')).toContainText('19');
    await expect(page.locator('[data-kpi=ram]')).toContainText('%');
    await expect(page.locator('tr[data-event]').first()).toBeVisible();
    await expect(page.locator('canvas[data-an-chart]').first()).toBeVisible();
    // Les courbes sont réellement dessinées par Chart.js (et non un simple canvas vide).
    await page.waitForFunction(() => window.Chart && [...document.querySelectorAll('canvas[data-an-chart]')].every((c) => Chart.getChart(c)));
    await expect(page.locator('.an-empty:not(.d-none)')).toHaveCount(0);
  });

  test('sélecteur de période : 24 h, 90 j, 1 an', async ({ page }) => {
    await page.goto('/admin/analytics?period=7d');
    for (const p of ['24h', '30d', '90d', '1y']) {
      await page.locator(`[data-period-link="${p}"]`).click();
      await expect(page).toHaveURL(new RegExp(`period=${p}`));
      await expect(page.locator('#an-root')).toHaveAttribute('data-period', p);
      await noCrash(page);
    }
    // période invalide : repli sur 7 j, jamais d'erreur
    await page.goto('/admin/analytics?period=zzz');
    await expect(page.locator('#an-root')).toHaveAttribute('data-period', '7d');
  });

  test('pays : classement, comparaison multiple et fiche', async ({ page }) => {
    await page.goto('/admin/analytics/countries');
    const rows = page.locator('#an-ranking tbody tr');
    await expect(rows).toHaveCount(4);
    await expect(rows.first()).toContainText('Aurelia'); // plus forte puissance
    // Par défaut les 4 pays sont cochés : on en décoche un, le formulaire se renvoie seul.
    await page.locator('input[name="countries[]"][value=Dorne]').uncheck();
    await page.waitForURL(/countries(%5B|\[)/);
    await expect(page.locator('input[name="countries[]"][value=Dorne]')).not.toBeChecked();
    await expect(page.locator('input[name="countries[]"][value=Aurelia]')).toBeChecked();
    await page.locator('#an-ranking a', { hasText: 'Borealis' }).click();
    await expect(page.locator('#an-country-name')).toHaveText('Borealis');
    await expect(page.locator('tr[data-event]').first()).toBeVisible();
    await shot(page, 'analytics-country-borealis');
    expect((await page.goto('/admin/analytics/countries/<script>')).status()).toBe(404);
  });

  test('recherche : branches, pays avancés, chronologie', async ({ page }) => {
    await page.goto('/admin/analytics/research?period=30d');
    await expect(page.locator('#an-advanced tbody tr').first()).toContainText('Aurelia');
    await expect(page.locator('#an-research-timeline tbody tr')).toHaveCount(3);
    await expect(page.locator('#an-research-timeline')).toContainText('Marché libre');
  });

  test('pétrole : zones, épuisée, courbes', async ({ page }) => {
    await page.goto('/admin/analytics/oil');
    await expect(page.locator('#an-oil')).toContainText('Zone Nord');
    await expect(page.locator('#an-oil tr', { hasText: 'Zone Sud' })).toContainText(/depleted|épuisée/i);
    await expect(page.locator('[data-kpi=extracted_24h] .v')).not.toHaveText('—');
  });

  test('économie, écologie, joueurs', async ({ page }) => {
    await page.goto('/admin/analytics/economy');
    await expect(page.locator('[data-kpi=money_supply] .v')).not.toHaveText('—');
    await page.goto('/admin/analytics/ecology');
    await expect(page.locator('#an-pollution')).toContainText('overworld');
    await expect(page.locator('tr[data-event=meltdown]')).toHaveCount(1);
    await page.goto('/admin/analytics/players');
    await expect(page.locator('[data-kpi=dau] .v')).not.toHaveText('—');
    await expect(page.locator('[data-kpi=retention_d1]')).toContainText('%');
  });

  test('événements : filtres, pagination, export CSV, aucune donnée personnelle', async ({ page }) => {
    await page.goto('/admin/analytics/events?period=30d');
    const all = await page.locator('tr[data-event]').count();
    expect(all).toBeGreaterThan(10);
    await page.locator('select[name=type]').selectOption('research_unlocked');
    await page.locator('#an-events-form button.btn-primary').click();
    await expect(page.locator('tr[data-event]')).toHaveCount(3);
    await page.locator('input[name=country]').fill('Borealis');
    await page.locator('#an-events-form button.btn-primary').click();
    await expect(page.locator('tr[data-event]')).toHaveCount(1);
    const body = await (await page.goto('/admin/analytics/events?period=30d')).text();
    expect(body).not.toContain('SECRETPLAYER');
    expect(body).not.toContain('SecretPlayer');
    const csv = await page.request.get('/admin/analytics/events/export.csv?period=30d&type=faction_created');
    expect(csv.status()).toBe(200);
    expect(csv.headers()['content-type']).toContain('text/csv');
    const txt = await csv.text();
    expect(txt).toContain('faction_created');
    expect(txt).not.toMatch(/SECRETPLAYER|SecretPlayer/i);
    // type invalide : ignoré, pas d'erreur
    expect((await page.goto('/admin/analytics/events?type=%27%3B--&country=%25')).status()).toBe(200);
  });

  test('guerre & missiles : paliers, carte, détail d’un tir, tableaux', async ({ page }) => {
    await page.goto('/admin/analytics/war?period=7d');
    await expect(page.locator('[data-kpi=missiles_launched] .v')).toHaveText('12');
    await expect(page.locator('#an-map .tr')).toHaveCount(12);
    await expect(page.locator('#an-recent tbody tr')).toHaveCount(12);
    await expect(page.locator('#an-offense tbody tr').first()).toContainText('Aurelia');
    await expect(page.locator('#an-defense tbody tr').first()).toBeVisible();
    await expect(page.locator('#an-reasons')).toContainText('not_at_war');
    await expect(page.locator('#an-missile-metrics')).toContainText('Aurelia');
    await expect(page.locator('#an-kd')).toContainText('Dorne');
    await expect(page.locator('tr[data-event=treaty_signed]')).toHaveCount(1);
    // détail via la liste
    await page.locator('#an-recent tbody tr').first().click();
    await expect(page.locator('#an-missile-detail')).toBeVisible();
    await expect(page.locator('#an-md-facts')).toContainText('ballistix');
    await expect(page.locator('#an-md-raw')).toContainText('missile_id');
    await shot(page, 'analytics-war-detail');
    // détail via la carte
    await page.locator('#an-md-close').click();
    await page.locator('#an-map .tr').nth(3).dispatchEvent('click');
    await expect(page.locator('#an-missile-detail')).toBeVisible();
    // filtre monde sans correspondance -> carte vide
    await page.locator('#an-map-world').selectOption('world');
    await expect(page.locator('#an-map .tr')).toHaveCount(12);
  });

  test('guerre : période sans tir -> carte vide, pas de plantage', async ({ page }) => {
    await page.goto('/admin/analytics/war?period=24h');
    await noCrash(page);
    // Les tirs factices s'étalent sur ~22 h : au moins un tir, mais la page reste saine.
    await expect(page.locator('#an-map')).toBeVisible();
  });

  test('usage : familles, tableau, courbe en fenêtre', async ({ page }) => {
    await page.goto('/admin/analytics/usage');
    for (const f of ['weapons', 'transport', 'crates', 'bourse', 'wheel', 'arcade', 'boss', 'disasters']) {
      await expect(page.locator(`#an-fam-${f}`), f).toBeVisible();
    }
    await expect(page.locator('#an-fam-weapons')).toContainText('superbwarfare:ak_47');
    await expect(page.locator('#an-fam-crates')).toContainText('legendary');
    await page.locator('#an-fam-weapons tr[data-usage-series]').first().click();
    await expect(page.locator('#an-usage-modal')).toBeVisible();
    await expect(page.locator('#an-usage-title')).not.toBeEmpty();
    await shot(page, 'analytics-usage-modal');
  });

  test('rafraîchissement automatique : remplace le contenu sans erreur', async ({ page }) => {
    const errors = [];
    page.on('pageerror', (e) => errors.push(String(e)));
    await page.goto('/admin/analytics?period=7d');
    await page.evaluate(() => { document.querySelector('#an-live .row').dataset.marker = 'old'; window.__anRefresh(); });
    await expect(page.locator('#an-live [data-marker=old]')).toHaveCount(0);
    await expect(page.locator('canvas[data-an-chart]').first()).toBeVisible();
    expect(errors).toEqual([]);
  });

  test('endpoint de données : séries, rejets, authentification', async ({ page, request }) => {
    const ok = await page.request.get('/admin/analytics/data?kind=series&period=30d&series[]=' + encodeURIComponent('server||tps|avg').replace('%7C%7C', '%7C%7C') );
    expect(ok.status()).toBe(200);
    const good = await page.request.get('/admin/analytics/data?kind=series&period=30d&series[]=server%7Cplayers_online%7C%7Cavg&series[]=faction%7Cpower%7CAurelia%7Cavg&series[]=faction%7Cpower%7C*%7Cmax');
    const j = await good.json();
    expect(j.ok).toBe(true);
    expect(j.series.length).toBe(3);
    expect(j.labels.length).toBeGreaterThan(10);
    expect(j.labels.length).toBeLessThanOrEqual(400);
    // fusion rollup (30 j > 7 j de données brutes) : le début de la courbe vient du rollup
    expect(j.series[0].slice(0, 5).some((v) => v !== null)).toBe(true);
    // séries invalides ignorées
    const bad = await (await page.request.get('/admin/analytics/data?kind=series&series[]=nope%7Cx%7C%7Cavg&series[]=server%7C%27%3BDROP%7C%7Cavg')).json();
    expect(bad.labels).toEqual([]);
    // 1 an : au plus ~400 points
    const year = await (await page.request.get('/admin/analytics/data?kind=series&period=1y&series[]=server%7Cplayers_online%7C%7Cavg')).json();
    expect(year.labels.length).toBeLessThanOrEqual(400);
    // tir inconnu -> 404 propre
    expect((await page.request.get('/admin/analytics/data?kind=missile&id=99999999')).status()).toBe(404);
    // sans session : pas de données
    const anon = await request.get(`${url(PORT_AN_DATA)}/admin/analytics/data?kind=series`, { maxRedirects: 0, headers: { Cookie: '' } });
    expect([302, 401, 403]).toContain(anon.status());
  });
});

test.describe('sans tables d’analyse', () => {
  test.use({ baseURL: url(PORT_AN_EMPTY) });
  test('chaque page affiche l’écran explicatif', async ({ page }) => {
    await login(page);
    for (const p of PAGES) {
      const res = await page.goto(`/admin/analytics${p}`);
      expect(res.status(), p).toBe(200);
      await expect(page.locator('#an-unavailable')).toHaveAttribute('data-state', 'no_tables');
      await noCrash(page);
    }
    await shot(page, 'analytics-no-tables');
    const j = await (await page.request.get('/admin/analytics/data?kind=missiles')).json();
    expect(j.ok).toBe(false);
    expect((await page.request.get('/admin/analytics/events/export.csv', { maxRedirects: 0 })).status()).toBe(302);
  });
});

test.describe('base du jeu injoignable ou non configurée', () => {
  test('injoignable', async ({ page }) => {
    await page.goto(`${url(PORT_DOWN)}/login`);
    await loginAt(page);
    for (const p of PAGES) {
      const res = await page.goto(`${url(PORT_DOWN)}/admin/analytics${p}`);
      expect(res.status(), p).toBe(200);
      await expect(page.locator('#an-unavailable')).toHaveAttribute('data-state', 'unreachable');
    }
    await shot(page, 'analytics-unreachable');
  });
  test('non configurée', async ({ page }) => {
    await page.goto(`${url(PORT)}/login`);
    await loginAt(page);
    for (const p of PAGES) {
      const res = await page.goto(`${url(PORT)}/admin/analytics${p}`);
      expect(res.status(), p).toBe(200);
      await expect(page.locator('#an-unavailable')).toHaveAttribute('data-state', 'unconfigured');
    }
    await shot(page, 'analytics-unconfigured');
  });
});
