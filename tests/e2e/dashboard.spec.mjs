import { test, expect } from '@playwright/test';
import { spawnSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { PORT_DOWN, panelEnv } from './global-setup.mjs';
import { shot } from './helpers.mjs';

// Tableau de bord public /joueurs : chaque onglet doit s'afficher proprement (états vides, jamais de 500)
// quand la base du jeu est ABSENTE (serveur principal) ou INJOIGNABLE (second serveur).
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const TABS = [
  ['classement', /Classement|Leaderboard/],
  ['pays', /Pays|Countries/],
  ['saison', /Saison|Season/],
  ['wonder', /Wonder/],
  ['collecte', /Collecte|Collection/],
  ['succes', /Succ[eè]s|Achievements/],
];
const hosts = [
  ['base du jeu absente', `http://127.0.0.1:${process.env.E2E_PORT || 8765}`, 'absente'],
  ['base du jeu injoignable', `http://127.0.0.1:${PORT_DOWN}`, 'injoignable'],
];

function tinker(code) {
  const r = spawnSync('php', ['artisan', 'tinker', `--execute=${code}`], { cwd: root, env: panelEnv(), encoding: 'utf8' });
  if (r.status !== 0) throw new Error(`tinker: ${r.stdout}${r.stderr}`);
}

for (const [label, base, slug] of hosts) {
  test.describe(`Tableau de bord joueurs (${label})`, () => {
    test.use({ baseURL: base });

    test('la page répond 200 avec SEO et sans erreur JS', async ({ page }) => {
      const errors = [];
      page.on('pageerror', (e) => errors.push(String(e)));
      const resp = await page.goto('/joueurs');
      expect(resp.status()).toBe(200);
      await expect(page).toHaveTitle(/Geoventure/);
      expect(await page.locator('meta[name=description]').getAttribute('content')).toBeTruthy();
      expect(await page.locator('meta[property="og:title"]').getAttribute('content')).toBeTruthy();
      await expect(page.locator('nav.tabs button')).toHaveCount(6);
      expect(errors).toEqual([]);
    });

    for (const [tab, title] of TABS) {
      test(`onglet ${tab} : état vide propre`, async ({ page }) => {
        await page.goto('/joueurs');
        await page.locator(`#btn-${tab}`).click();
        const panel = page.locator(`#tab-${tab}`);
        await expect(panel).toBeVisible();
        await expect(page.locator(`#btn-${tab}`)).toHaveText(title);
        // base du jeu indisponible : un message vide, pas d'erreur serveur affichée
        if (tab !== 'succes') await expect(panel.locator('.empty').first()).toBeVisible();
        await expect(page.locator('body')).not.toContainText(/Whoops|Server Error|SQLSTATE|Exception/);
        await shot(page, `dashboard-${slug}-${tab}`);
      });
    }

    test('onglet via ?tab= et alias /dashboard', async ({ page }) => {
      await page.goto('/joueurs?tab=wonder');
      await expect(page.locator('#tab-wonder')).toBeVisible();
      await expect(page.locator('#tab-classement')).toBeHidden();
      const r = await page.goto('/dashboard?tab=pays');
      expect(r.status()).toBe(200);
      await expect(page.locator('#tab-pays')).toBeVisible();
    });

    test('profil inconnu : 404 propre', async ({ page }) => {
      for (const name of ['Fantome_404', '<script>alert(1)</script>', 'a'.repeat(60)]) {
        const r = await page.goto(`/joueurs/${encodeURIComponent(name)}`);
        expect(r.status()).toBe(404);
        await expect(page.locator('.empty')).toBeVisible();
        await expect(page.locator('body')).not.toContainText(/Whoops|Exception/);
      }
      await shot(page, `dashboard-${slug}-profil-404`);
    });

    test('mobile : pas de défilement horizontal', async ({ page }) => {
      await page.setViewportSize({ width: 375, height: 760 });
      await page.goto('/joueurs');
      const over = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1);
      expect(over).toBe(false);
      await shot(page, `dashboard-${slug}-mobile`);
    });

    test('langue en : libellés anglais', async ({ page }) => {
      await page.goto('/lang/en');
      await page.goto('/joueurs');
      await expect(page.locator('#btn-pays')).toHaveText('Countries');
    });
  });
}

test.describe('Tableau de bord joueurs : profil et échappement', () => {
  test.beforeAll(() => {
    tinker(`\\App\\Models\\Achievement::create(['code'=>'e2e_xss','name'=>'<b>Gras</b>"x','description'=>'<img src=x onerror=alert(1)>','icon'=>null,'points'=>5,'rarity'=>'epic','category'=>'E2E','condition_type'=>'manual','condition_value'=>1,'active'=>true,'secret'=>false,'max_level'=>1]);`
      + `\\App\\Models\\Achievement::create(['code'=>'e2e_secret','name'=>'TopSecretNom','description'=>'TopSecretDesc','icon'=>null,'points'=>9,'rarity'=>'legendary','category'=>'E2E','condition_type'=>'manual','condition_value'=>1,'active'=>true,'secret'=>true,'max_level'=>1]);`
      + `\\App\\Models\\AchievementUnlock::create(['player'=>'Alice_E2E','code'=>'e2e_xss','unlocked_at'=>now()]);`
      + `\\App\\Models\\AchievementUnlock::create(['player'=>'Alice_E2E','code'=>'e2e_secret','unlocked_at'=>now()]);`);
  });
  test.afterAll(() => {
    tinker(`\\App\\Models\\AchievementUnlock::where('player','Alice_E2E')->delete();\\App\\Models\\Achievement::whereIn('code',['e2e_xss','e2e_secret'])->delete();`);
  });

  test('catalogue : échappé, secrets masqués', async ({ page }) => {
    let dialog = false;
    page.on('dialog', (d) => { dialog = true; d.dismiss(); });
    await page.goto('/joueurs?tab=succes');
    const panel = page.locator('#tab-succes');
    await expect(panel).toContainText('<b>Gras</b>"x');
    await expect(panel.locator('b')).toHaveCount(0);
    await expect(panel).not.toContainText('TopSecretNom');
    await expect(panel).not.toContainText('TopSecretDesc');
    await expect(panel).toContainText('???');
    expect(dialog).toBe(false);
    await shot(page, 'dashboard-succes-rempli');
  });

  test('profil public : succès et points, pas de donnée personnelle', async ({ page }) => {
    const r = await page.goto('/joueurs/alice_e2e');
    expect(r.status()).toBe(200);
    await expect(page.locator('h2').first()).toHaveText('alice_e2e');
    await expect(page.locator('body')).toContainText('<b>Gras</b>"x');
    await expect(page.locator('body')).not.toContainText('TopSecretNom');
    await expect(page.locator('body')).toContainText('14'); // 5 + 9 points
    const html = await page.content();
    expect(html).not.toContain('e2e-admin@');
    await shot(page, 'dashboard-profil');
  });
});
