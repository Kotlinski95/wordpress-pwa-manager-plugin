import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { findAuthorMismatch, findMismatch, readAuthors, readVersions } from './lib-version.mjs';

const root = resolve(fileURLToPath(new URL('.', import.meta.url)), '..');
const versions = readVersions(root, process.env.GITHUB_REF_NAME ?? '');
const problem = findMismatch(versions) ?? findAuthorMismatch(readAuthors(root));

if (problem) {
  console.error(problem);
  process.exit(1);
}

console.log(`Version ${Object.values(versions)[0]} consistent across ${Object.keys(versions).join(', ')}`);
