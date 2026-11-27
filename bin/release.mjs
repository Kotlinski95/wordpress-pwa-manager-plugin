import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { applyVersion, bumpVersion, findMismatch, isPromotion, readVersions } from './lib-version.mjs';

const root = resolve(fileURLToPath(new URL('.', import.meta.url)), '..');
const args = process.argv.slice(2);
const dryRun = args.includes('--dry-run');
const preIndex = args.indexOf('--pre');
const pre = preIndex === -1 ? null : (args[preIndex + 1] ?? '');
const level = ['--major', '--minor', '--patch'].find((flag) => args.includes(flag))?.slice(2) ?? 'patch';

const versions = readVersions(root);
const problem = findMismatch(versions);

if (problem) {
  console.error(problem);
  process.exit(1);
}

const current = Object.values(versions)[0];
let next;
try {
  next = bumpVersion(current, level, pre);
} catch (error) {
  console.error(error.message);
  process.exit(1);
}
const promoted = isPromotion(current, next);
const date = new Date().toISOString().slice(0, 10);

console.log(`${dryRun ? 'Dry run: would bump' : 'Bumping'} ${current} -> ${next} (${pre === null ? level : `${level}, ${pre}`}) dated ${date}`);
console.log('Files: wordpress-pwa-manager.php, package.json, package-lock.json, CHANGELOG.md');

if (dryRun) {
  process.exit(0);
}

try {
  applyVersion(root, next, date, promoted ? `- Stable release of ${next}, promoted from ${current}.` : null);
} catch (error) {
  console.error(error.message);
  process.exit(1);
}

console.log(`Next: git commit -am "chore: release v${next}" && git tag v${next} && git push --follow-tags`);
