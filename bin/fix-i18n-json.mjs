import { createHash } from 'node:crypto';
import { readdir, readFile, rename, writeFile } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const languages = resolve(root, 'languages');
const scriptPaths = (await readdir(resolve(root, 'build')))
  .filter((name) => name.endsWith('.js'))
  .map((name) => `build/${name}`);

const md5 = (value) => createHash('md5').update(value).digest('hex');

const resolveSource = (source) => {
  if (scriptPaths.includes(source)) {
    return source;
  }
  const stem = source.replace(/\.js$/, '');
  const candidates = scriptPaths.filter((path) => path.startsWith(stem) && path.endsWith('min.js'));
  return candidates.length === 1 ? candidates[0] : source;
};

const files = (await readdir(languages)).filter((name) => /-[0-9a-f]{32}\.json$/.test(name));

for (const name of files) {
  const file = resolve(languages, name);
  const data = JSON.parse(await readFile(file, 'utf8'));
  const source = resolveSource(data.source);
  if (source === data.source) {
    continue;
  }
  data.source = source;
  const target = name.replace(/-[0-9a-f]{32}\.json$/, `-${md5(source)}.json`);
  await writeFile(file, JSON.stringify(data));
  await rename(file, resolve(languages, target));
}
