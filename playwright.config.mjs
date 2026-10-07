import { defineConfig } from '@playwright/test';

const PORT = process.env.E2E_PORT || '8765';
export default defineConfig({
  testDir: 'tests/e2e',
  testMatch: '**/*.spec.mjs',
  globalSetup: './tests/e2e/global-setup.mjs',
  globalTeardown: './tests/e2e/global-teardown.mjs',
  workers: 1,
  timeout: 30000,
  reporter: [['list']],
  outputDir: 'tests/e2e/.tmp/results',
  use: {
    baseURL: `http://127.0.0.1:${PORT}`,
    viewport: { width: 1366, height: 800 },
    launchOptions: { executablePath: process.env.CHROMIUM_PATH || '/opt/pw-browsers/chromium', args: ['--no-sandbox'] },
  },
});
