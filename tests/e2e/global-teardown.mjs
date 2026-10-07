import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
export default async function globalTeardown() {
  const f = path.join(here, '.tmp', 'state.json');
  if (!fs.existsSync(f)) return;
  const st = JSON.parse(fs.readFileSync(f, 'utf8'));
  for (const pid of st.pids || []) {
    try { process.kill(-pid, 'SIGTERM'); } catch { try { process.kill(pid, 'SIGTERM'); } catch {} }
  }
  if (st.markerCreated) fs.rmSync(path.join(here, '..', '..', 'storage/installed'), { force: true });
}
