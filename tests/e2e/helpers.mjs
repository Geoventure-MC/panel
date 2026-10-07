import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { ADMIN } from './global-setup.mjs';

export const SHOTS = path.join(path.dirname(fileURLToPath(import.meta.url)), 'screenshots');
fs.mkdirSync(SHOTS, { recursive: true });
export { ADMIN };

export async function login(page) {
  await page.goto('/login');
  await page.locator('input[name=email]').fill(ADMIN.email);
  await page.locator('input[name=password]').fill(ADMIN.password);
  await Promise.all([page.waitForURL(/\/admin/), page.locator('form button[type=submit], form input[type=submit]').first().click()]);
}

export const shot = (page, name) => page.screenshot({ path: path.join(SHOTS, `${name}.png`), fullPage: true });
