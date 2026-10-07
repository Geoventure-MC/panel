import { test, expect } from '@playwright/test';
import { login, shot } from './helpers.mjs';

const PAGES = [
  ['dashboard', '/admin'], ['dashboard-live', '/admin/dashboard/live'], ['general', '/admin/general'],
  ['security', '/admin/security'], ['server', '/admin/server'], ['ui', '/admin/ui'], ['whitelist', '/admin/whitelist'],
  ['ignore', '/admin/ignore'], ['mods', '/admin/mods'], ['loader', '/admin/loader'], ['rpc', '/admin/rpc'],
  ['users', '/admin/users'], ['bg', '/admin/bg'], ['notifications', '/admin/notifications'],
  ['changelog', '/admin/changelog'], ['community-mods', '/admin/community-mods'], ['achievements', '/admin/achievements'],
  ['seasons', '/admin/seasons'], ['wonder', '/admin/wonder'], ['scheduled-events', '/admin/scheduled-events'],
  ['audit', '/admin/audit'], ['game-commands', '/admin/game-commands'], ['stats', '/admin/stats'],
  ['launcher-content', '/admin/launcher-content'], ['two-factor', '/admin/two-factor'], ['config', '/admin/config'],
  ['sso', '/admin/sso'], ['update', '/admin/update'],
];

test.describe('admin', () => {
  test('login refusé avec un mauvais mot de passe', async ({ page }) => {
    await page.goto('/login');
    await page.locator('input[name=email]').fill('nobody@example.test');
    await page.locator('input[name=password]').fill('mauvais');
    await page.locator('form button[type=submit], form input[type=submit]').first().click();
    await expect(page).toHaveURL(/\/login/);
    await shot(page, 'login-refuse');
  });

  test('admin protégé sans session', async ({ request }) => {
    const r = await request.get('/admin', { maxRedirects: 0 });
    expect([301, 302]).toContain(r.status());
    expect(r.headers().location).toMatch(/login/);
  });

  test('login puis toutes les pages admin répondent 200 sans erreur JS', async ({ page }) => {
    await login(page);
    await shot(page, 'admin-dashboard');
    const problems = [];
    page.on('pageerror', e => problems.push(`pageerror: ${e.message}`));
    for (const [name, url] of PAGES) {
      const resp = await page.goto(url);
      const st = resp.status();
      if (st !== 200) problems.push(`${url} -> HTTP ${st}`);
      await shot(page, `admin-${name}`);
    }
    expect(problems, problems.join('\n')).toEqual([]);
  });
});
