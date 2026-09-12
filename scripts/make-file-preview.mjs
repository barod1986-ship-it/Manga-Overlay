import { readFile, readdir, writeFile } from 'node:fs/promises';
import { Script } from 'node:vm';
import { fileURLToPath } from 'node:url';
import { resolve } from 'node:path';

const root = fileURLToPath(new URL('../', import.meta.url));
const output = resolve(root, 'wp-content/plugins/manga-overlay-core/assets/dist/poc');
const html = await readFile(resolve(output, 'index.html'), 'utf8');
// Fail rather than produce a broken file preview if Vite starts emitting module-only syntax.
for (const file of await readdir(resolve(output, 'assets'))) {
  if (file.endsWith('.js')) new Script(await readFile(resolve(output, 'assets', file), 'utf8'));
}
await writeFile(resolve(output, 'preview.html'), html.replaceAll(' type="module"', '').replaceAll(' crossorigin', ''));
console.log('Created preview.html for local, session-only inspection.');
