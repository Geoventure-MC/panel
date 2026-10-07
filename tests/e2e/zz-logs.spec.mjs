import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

// Dernier fichier joué : aucune exception serveur (local.ERROR) ne doit avoir été journalisée pendant la suite.
test('laravel.log : aucune exception serveur pendant les tests', async () => {
  const log = path.join(path.dirname(fileURLToPath(import.meta.url)), '..', '..', 'storage/logs/laravel.log');
  const errors = fs.readFileSync(log, 'utf8').split('\n').filter(l => /\.(ERROR|CRITICAL|EMERGENCY):/.test(l)).map(l => l.slice(0, 300));
  expect(errors, errors.join('\n')).toEqual([]);
});
