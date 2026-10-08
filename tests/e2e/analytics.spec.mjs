// Geoventure Analytics : pages d'analyse lues dans la base du jeu (SQLite jetable branchée sur la connexion `game`).
import { test, expect } from '@playwright/test';
import { login, shot, ADMIN } from './helpers.mjs';
import { PORT, PORT_DOWN, PORT_AN_DATA, PORT_AN_EMPTY, PORT_AN_NOCAT } from './global-setup.mjs';

const url = (port) => `http://127.0.0.1:${port}`;
const NEW_PAGES = ['/health', '/peak', '/compare', '/territories', '/diplomacy', '/logistics', '/conflicts', '/retention', '/rankings', '/achievements'];
const PAGES = ['', '/countries', '/countries/Aurelia', '/research', '/oil', '/economy', '/ecology', '/players', '/events', '/war', '/usage'];
// login sur une URL absolue (serveur autre que le baseURL par défaut)
const loginAt = async (page) => {
  await page.locator('input[name=email]').fill(ADMIN.email);
  await page.locator('input[name=password]').fill(ADMIN.password);
  await Promise.all([page.waitForURL(/\/admin/), page.locator('form button[type=submit], form input[type=submit]').first().click()]);
};
// Aucun identifiant brut dans le texte affiché ni dans les libellés des graphiques.
const RAW = [/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i, /\b[a-z][a-z0-9]*(_[a-z0-9]+)+\b/, /\b[a-z][a-z0-9]+:[a-z][a-z0-9_]+\b/];
const noRawIds = async (page, where) => {
  const text = await page.locator('#an-root').innerText();
  const labels = await page.$$eval('canvas[data-an-chart]', (cs) => cs.map((c) => {
    const sp = JSON.parse(c.getAttribute('data-an-chart'));
    return [...(sp.inline ? sp.inline.labels.filter((l) => typeof l === 'string') : []), ...(sp.inline ? sp.inline.datasets : sp.series || []).map((d) => d.label)].join('\n');
  }));
  const all = text + '\n' + labels.join('\n');
  for (const re of RAW) {
    const m = all.match(re);
    expect(m ? m[0] : null, `${where} : identifiant brut affiché`).toBeNull();
  }
};
const noCrash = async (page) => {
  const body = await page.locator('body').innerText();
  expect(body).not.toMatch(/Whoops|Illuminate\\|SQLSTATE|Stack trace|Server Error/);
};

test.describe('avec données', () => {
  test.use({ baseURL: url(PORT_AN_DATA) });

  test.beforeEach(async ({ page }) => { await login(page); });

  test('chaque page répond, affiche ses blocs et ses courbes', async ({ page }) => {
    test.setTimeout(180000);
    const errors = [];
    page.on('pageerror', (e) => errors.push(String(e)));
    for (const p of [...PAGES, ...NEW_PAGES]) {
      const res = await page.goto(`/admin/analytics${p}?period=7d`);
      expect(res.status(), p).toBe(200);
      await noCrash(page);
      await expect(page.locator('#an-unavailable')).toHaveCount(0);
      await page.waitForTimeout(900);
      await shot(page, `analytics-${p.replace(/\//g, '-').replace(/^-/, '') || 'overview'}`);
    }
    expect(errors).toEqual([]);
  });

  test("vue d'ensemble : KPI, courbes et fil d'événements", async ({ page }) => {
    await page.goto('/admin/analytics?period=7d');
    await expect(page.locator('[data-kpi=players_online] .v')).not.toHaveText('—');
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
    await expect(page.locator('#an-research-timeline tbody tr')).toHaveCount(4);
    await expect(page.locator('#an-research-matrix')).toContainText('Économie');
    await expect(page.locator('#an-research-matrix')).toContainText('Antiquité');
    await expect(page.locator('#an-research-timeline')).toContainText('Marché libre');
  });

  test('pétrole : zones, épuisée, courbes', async ({ page }) => {
    await page.goto('/admin/analytics/oil');
    await expect(page.locator('[data-oil-zone]')).toHaveCount(3);
    await expect(page.locator('#an-oil')).toContainText('Golfe Nord');
    await expect(page.locator('[data-oil-zone]', { hasText: 'Désert du Sud' })).toContainText(/épuisée/i);
    await expect(page.locator('[data-oil-zone]', { hasText: 'Golfe Nord' })).toContainText('Terre');
    await expect(page.locator('[data-kpi=extracted_24h] .v')).not.toHaveText('—');
  });

  test('économie, écologie, joueurs', async ({ page }) => {
    await page.goto('/admin/analytics/economy');
    await expect(page.locator('[data-kpi=money_supply] .v')).not.toHaveText('—');
    await page.goto('/admin/analytics/ecology');
    await expect(page.locator('#an-pollution')).toContainText('Terre');
    await expect(page.locator('tr[data-event=meltdown]')).toHaveCount(1);
    await page.goto('/admin/analytics/players');
    await expect(page.locator('[data-kpi=dau] .v')).not.toHaveText('—');
    await expect(page.locator('[data-kpi=retention_d1]')).toContainText('%');
  });

  test('menu regroupé par catégories', async ({ page }) => {
    await page.goto('/admin/analytics');
    await expect(page.locator('.an-menu .grp')).toHaveCount(6);
    await expect(page.locator('.an-menu a')).toHaveCount(20);
  });

  test('heures de pointe : carte de chaleur jour × heure', async ({ page }) => {
    await page.goto('/admin/analytics/peak');
    await expect(page.locator('#an-heatmap tbody tr')).toHaveCount(7);
    await expect(page.locator('#an-peak-summary')).toContainText('Créneau le plus calme');
  });

  test('territoires : carte des chunks, date choisie', async ({ page }) => {
    await page.goto('/admin/analytics/territories');
    await expect(page.locator('#an-territory-map rect').first()).toBeVisible();
    await expect(page.locator('#an-territory-stats tbody tr')).toHaveCount(4);
    const today = await page.locator('#an-territory-stats tbody tr').first().innerText();
    // il y a 20 jours : aucun instantané
    const old = new Date(Date.now() - 20 * 86400e3).toISOString().slice(0, 10);
    await page.goto(`/admin/analytics/territories?date=${old}`);
    await expect(page.locator('#an-territory-empty')).toBeVisible();
    // il y a 5 jours : l'instantané ancien (moins de chunks pour Aurelia)
    const mid = new Date(Date.now() - 5 * 86400e3).toISOString().slice(0, 10);
    await page.goto(`/admin/analytics/territories?date=${mid}`);
    expect(await page.locator('#an-territory-stats tbody tr').first().innerText()).not.toBe(today);
    await page.goto('/admin/analytics/territories?date=zzz');
    await noCrash(page);
  });

  test('comparateur : radar de pays', async ({ page }) => {
    await page.goto('/admin/analytics/compare?countries[]=Aurelia&countries[]=Dorne');
    await expect(page.locator('#an-compare-table thead th')).toHaveCount(3);
    await page.waitForFunction(() => window.Chart && Chart.getChart(document.querySelector('canvas[data-an-chart]')));
    await expect(page.locator('.an-empty:not(.d-none)')).toHaveCount(0);
  });

  test('diplomatie, conflits, rétention, classements, succès, logistique, santé', async ({ page }) => {
    await page.goto('/admin/analytics/diplomacy');
    await expect(page.locator('#an-alliances')).toContainText('Aurelia');
    await expect(page.locator('#an-diplo circle')).toHaveCount(4);
    await page.goto('/admin/analytics/conflicts?period=90d');
    await expect(page.locator('#an-wars-table tbody tr')).toHaveCount(3);
    await expect(page.locator('#an-wars-table')).toContainText('Victoire de Cendra');
    await page.goto('/admin/analytics/retention');
    await expect(page.locator('#an-cohorts tbody tr')).toHaveCount(4);
    await page.goto('/admin/analytics/rankings');
    await expect(page.locator('#an-rankings')).toContainText('Alyx');
    await expect(page.locator('#an-rankings')).toContainText('Joueur #3f2b8c1e');
    await page.goto('/admin/analytics/achievements');
    await expect(page.locator('#an-achievements')).toContainText('Hiver nucléaire');
    await page.goto('/admin/analytics/logistics');
    await expect(page.locator('[data-kpi=convoys_active] .v')).not.toHaveText('—');
    await page.goto('/admin/analytics/health');
    await expect(page.locator('[data-kpi=gc_pause_ms] .v')).not.toHaveText('—');
    await page.goto('/admin/analytics/economy');
    await expect(page.locator('#an-econ-countries tbody tr')).toHaveCount(4);
    await expect(page.locator('[data-kpi=gini] .v')).not.toHaveText('—');
  });

  test('événements : filtres, pagination, export CSV, aucune donnée personnelle', async ({ page }) => {
    await page.goto('/admin/analytics/events?period=30d');
    const all = await page.locator('tr[data-event]').count();
    expect(all).toBeGreaterThan(10);
    await page.locator('select[name=type]').selectOption('research_unlocked');
    await page.locator('#an-events-form button.btn-primary').click();
    await expect(page.locator('tr[data-event]')).toHaveCount(4);
    await page.locator('select[name=country]').selectOption('Borealis');
    await page.locator('#an-events-form button.btn-primary').click();
    await expect(page.locator('tr[data-event]')).toHaveCount(1);
    const body = await (await page.goto('/admin/analytics/events?period=30d')).text();
    expect(body).not.toContain('SECRETPLAYER');
    expect(body).not.toContain('SecretPlayer');
    const csv = await page.request.get('/admin/analytics/events/export.csv?period=30d&type=faction_created');
    expect(csv.status()).toBe(200);
    expect(csv.headers()['content-type']).toContain('text/csv');
    const txt = await csv.text();
    expect(txt).toContain('Pays créé');
    expect(txt).not.toContain('faction_created');
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
    await expect(page.locator('#an-reasons')).toContainText('Pas en guerre');
    await expect(page.locator('#an-missile-metrics')).toContainText('Aurelia');
    await expect(page.locator('#an-kd')).toContainText('Dorne');
    await expect(page.locator('tr[data-event=treaty_signed]')).toHaveCount(1);
    // détail via la liste
    await page.locator('#an-recent tbody tr').first().click();
    await expect(page.locator('#an-missile-detail')).toBeVisible();
    await expect(page.locator('#an-md-facts')).toContainText(/Charge explosive|Bombe nucléaire|fragmentation|Impulsion/);
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
    await expect(page.locator('#an-fam-weapons')).toContainText('AK-47');
    await expect(page.locator('#an-fam-crates')).toContainText('Caisse légendaire');
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

test.describe('noms lisibles partout', () => {
  test.describe('avec catalogue', () => {
    test.use({ baseURL: url(PORT_AN_DATA) });
    test('aucun identifiant brut sur aucune page', async ({ page }) => {
      test.setTimeout(180000);
      await login(page);
      for (const p of [...PAGES, ...NEW_PAGES]) {
        await page.goto(`/admin/analytics${p}?period=30d`);
        await page.waitForTimeout(400);
        await noRawIds(page, p || '/');
      }
    });
    test('le catalogue fournit les libellés de métriques', async ({ page }) => {
      await login(page);
      await page.goto('/admin/analytics');
      await expect(page.locator('[data-kpi=players_online]')).toContainText('Joueurs connectés');
    });
  });
  test.describe('sans table gf_catalog (repli)', () => {
    test.use({ baseURL: url(PORT_AN_NOCAT) });
    test('repli lisible, aucune erreur, aucun identifiant brut', async ({ page }) => {
      test.setTimeout(180000);
      await login(page);
      for (const p of [...PAGES, ...NEW_PAGES]) {
        const res = await page.goto(`/admin/analytics${p}?period=30d`);
        expect(res.status(), p).toBe(200);
        await noCrash(page);
        await page.waitForTimeout(400);
        await noRawIds(page, p || '/');
      }
      await page.goto('/admin/analytics/oil');
      await expect(page.locator('#an-oil')).toContainText('Golfe Nord');
      await page.goto('/admin/analytics/usage');
      await expect(page.locator('#an-fam-weapons')).toContainText('Ak 47');
      await page.goto('/admin/analytics');
      await expect(page.locator('[data-kpi=players_online]')).toContainText('Joueurs en ligne');
      await shot(page, 'analytics-no-catalog');
    });
  });
});

test.describe('sans tables d’analyse', () => {
  test.use({ baseURL: url(PORT_AN_EMPTY) });
  test('chaque page affiche l’écran explicatif', async ({ page }) => {
    await login(page);
    for (const p of [...PAGES, ...NEW_PAGES]) {
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
    for (const p of [...PAGES, ...NEW_PAGES]) {
      const res = await page.goto(`${url(PORT_DOWN)}/admin/analytics${p}`);
      expect(res.status(), p).toBe(200);
      await expect(page.locator('#an-unavailable')).toHaveAttribute('data-state', 'unreachable');
    }
    await shot(page, 'analytics-unreachable');
  });
  test('non configurée', async ({ page }) => {
    await page.goto(`${url(PORT)}/login`);
    await loginAt(page);
    for (const p of [...PAGES, ...NEW_PAGES]) {
      const res = await page.goto(`${url(PORT)}/admin/analytics${p}`);
      expect(res.status(), p).toBe(200);
      await expect(page.locator('#an-unavailable')).toHaveAttribute('data-state', 'unconfigured');
    }
    await shot(page, 'analytics-unconfigured');
  });
});
