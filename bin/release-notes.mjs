import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { extractReleaseNotes } from './lib-version.mjs';

const root = resolve(fileURLToPath(new URL('.', import.meta.url)), '..');
const version = (process.argv[2] ?? '').replace(/^v/, '');

try {
  const changelog = readFileSync(resolve(root, 'CHANGELOG.md'), 'utf8');
  process.stdout.write(`${extractReleaseNotes(changelog, version)}\n`);
} catch (error) {
  console.error(error.message);
  process.exit(1);
}
