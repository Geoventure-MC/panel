import { test, expect } from '@playwright/test';
import { login, shot } from './helpers.mjs';

test.describe.configure({ mode: 'serial' });

test.describe('parcours admin → API publique', () => {
  test.beforeEach(async ({ page }) => {
    page.on('dialog', d => d.accept());
    await login(page);
  });

  test('annonce : création, exposition /utils/notifications, désactivation, suppression', async ({ page, request }) => {
    await page.goto('/admin/notifications');
    const form = page.locator('form[action$="/admin/notifications"]');
    await form.locator('select[name=type]').selectOption('event');
    await form.locator('textarea[name=message]').fill('Annonce E2E : tournoi samedi');
    await form.locator('input[name=url]').fill('https://example.test/tournoi');
    await form.locator('button:not([type=button])').click();
    await expect(page.getByText('Annonce E2E : tournoi samedi').first()).toBeVisible();
    await shot(page, 'flow-notification-creee');

    const list = await (await request.get('/utils/notifications')).json();
    const n = list.find(x => x.message.includes('Annonce E2E'));
    expect(n, JSON.stringify(list)).toBeTruthy();
    for (const k of ['id', 'type', 'message', 'url', 'expiresAt', 'createdAt']) expect(n, k).toHaveProperty(k);
    expect(n.type).toBe('event');

    await page.locator('form[action*="/toggle"] button').first().click();
    const after = await (await request.get('/utils/notifications')).json();
    expect(after.find(x => x.message.includes('Annonce E2E'))).toBeFalsy();

    await page.locator('form[action*="/admin/notifications/"][method=POST]:has(input[name=_method][value=DELETE]) button').first().click();
    await expect(page.getByText('Annonce E2E : tournoi samedi')).toHaveCount(0);
  });

  test('succès : création (niveaux, secret) et masquage dans /utils/achievements', async ({ page, request }) => {
    await page.goto('/admin/achievements');
    const f = page.locator('#achievement-form');
    await f.locator('input[name=code]').fill('e2e_secret');
    await f.locator('input[name=name]').fill('Succès E2E secret');
    await f.locator('input[name=points]').fill('25');
    await f.locator('select[name=condition_type]').selectOption('manual');
    await f.locator('input[name=max_level]').fill('3');
    const secret = f.locator('input[name=secret][type=checkbox]');
    if (await secret.count()) await secret.check();
    await f.locator('button:not([type=button])').click();
    await expect(page.locator('body')).toContainText('e2e_secret');
    await shot(page, 'flow-succes-cree');

    const list = await (await request.get('/utils/achievements')).json();
    const a = list.find(x => x.code === 'e2e_secret');
    expect(a).toBeTruthy();
    expect(a.max_level).toBe(3);
    if (await secret.count()) {
      expect(a.secret).toBe(true);
      expect(a.name).toBe('???');
      expect(a.description || '').toBe('');
    }
  });

  test('Wonder : édition, équipe, exposition /utils/wonder (base du jeu absente tolérée)', async ({ page, request }) => {
    await page.goto('/admin/wonder');
    const f = page.locator('form[action$="/admin/wonder"]');
    await f.locator('input[name=name]').fill('Édition E2E');
    await f.locator('input[name=theme]').fill('Cités flottantes');
    await f.locator('input[name=opens_at]').fill('2030-01-01T10:00');
    await f.locator('input[name=closes_at]').fill('2030-01-08T10:00');
    await f.locator('button:not([type=button])').click();
    await expect(page).toHaveURL(/edition=\d+/);
    await expect(page.locator('body')).toContainText('Édition E2E');
    await shot(page, 'flow-wonder-edition');

    const t = page.locator('form[action*="/teams"][action*="/wonder/"]').filter({ has: page.locator('input[name=color]') }).last();
    await t.locator('input[name=name]').fill('Les Bâtisseurs');
    await t.locator('input[name=color]').fill('#3366ff');
    await t.locator('button:not([type=button])').click();
    await expect(page.locator('body')).toContainText('Les Bâtisseurs');
    await shot(page, 'flow-wonder-equipe');

    const w = await (await request.get('/utils/wonder')).json();
    expect(w.current, JSON.stringify(w)).toBeTruthy();
    expect(w.current.name).toBe('Édition E2E');
    expect(w.current.ranking ?? []).toHaveLength(0); // pas de classement avant publication
  });

  test('commande jeu sans base du jeu : refus propre, pas de 500', async ({ page }) => {
    await page.goto('/admin/game-commands');
    const f = page.locator('form[action$="/admin/game-commands"]');
    if (await f.locator('button:not([type=button]):not([disabled])').count()) {
      await f.locator('select[name=type]').selectOption('broadcast');
      await f.locator('input[name=target]').fill('Message E2E');
      const [resp] = await Promise.all([page.waitForResponse(r => r.url().endsWith('/admin/game-commands') && r.request().method() === 'POST'), f.locator('button:not([type=button])').click()]);
      expect(resp.status()).toBeLessThan(500);
    }
    await shot(page, 'flow-commande-jeu');
    await expect(page.locator('body')).not.toContainText('Whoops');
  });

  test('maintenance : bascule visible dans /utils/api puis retour', async ({ page, request }) => {
    await page.goto('/admin/security');
    const form = page.locator('form[action$="/admin/maintenance/toggle"]');
    await form.locator('button:not([type=button])').first().click();
    expect((await (await request.get('/utils/api')).json()).maintenance).toBe(true);
    await shot(page, 'flow-maintenance-on');
    await page.goto('/admin/security');
    await page.locator('form[action$="/admin/maintenance/toggle"] button:not([type=button])').first().click();
    expect((await (await request.get('/utils/api')).json()).maintenance).toBe(false);
  });

  test('serveur ajouté : /utils/servers-status, /utils/api et page /status cohérents', async ({ page, request }) => {
    await page.goto('/admin/server');
    await page.locator('[data-bs-target="#addServerForm"]').click();
    const f = page.locator('form[action$="/admin/server/add"]');
    await f.locator('input[name=server_name]').fill('Geoventure');
    await f.locator('input[name=server_ip]').fill('127.0.0.1');
    await f.locator('input[name=server_port]').fill('25999');
    const slug = f.locator('input[name=instance_slug]');
    if (await slug.count()) await slug.fill('geoventure');
    await f.locator('button:not([type=button])').click();
    await shot(page, 'flow-serveur-ajoute');

    const st = await (await request.get('/utils/servers-status')).json();
    expect(st.length).toBe(1);
    expect(st[0]).toMatchObject({ id: 'geoventure', name: 'Geoventure', online: false });
    const api = await (await request.get('/utils/api?instance=geoventure')).json();
    expect(api.instance ?? 'geoventure').toBe('geoventure');
    const r = await page.goto('/status');
    expect(r.status()).toBe(200);
    await expect(page.locator('body')).toContainText('Geoventure');
    await shot(page, 'public-status-serveur');
    // Instance inconnue : repli global, jamais d'erreur.
    expect((await request.get('/utils/api?instance=nexiste-pas')).status()).toBe(200);
  });
});
