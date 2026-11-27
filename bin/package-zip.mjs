import { execFileSync } from 'node:child_process';
import { copyFileSync, createReadStream, existsSync, mkdirSync, readFileSync, rmSync, statSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(fileURLToPath(new URL('.', import.meta.url)), '..');
const slug = 'wordpress-pwa-manager';
const dist = resolve(root, 'dist');
const stage = resolve(dist, '.stage');
const target = resolve(dist, slug);
const zipPath = resolve(dist, `${slug}.zip`);
const required = ['build/frontend.js', 'build/admin.js', 'vendor/autoload.php', `${slug}.php`];
const skipBuild = process.argv.includes('--skip-build');

const run = (command, args, cwd = root) => execFileSync(command, args, { cwd, stdio: 'inherit' });

const sha256 = (file) =>
  new Promise((done, fail) => {
    const hash = createHash('sha256');
    createReadStream(file)
      .on('data', (chunk) => hash.update(chunk))
      .on('end', () => done(hash.digest('hex')))
      .on('error', fail);
  });

if (!skipBuild) {
  run('npm', ['run', 'build']);
}
run('node', ['bin/check-version.mjs']);

rmSync(dist, { recursive: true, force: true });
mkdirSync(stage, { recursive: true });
copyFileSync(resolve(root, 'composer.json'), resolve(stage, 'composer.json'));
copyFileSync(resolve(root, 'composer.lock'), resolve(stage, 'composer.lock'));
run('composer', ['install', '--no-dev', '-o', '--prefer-dist', '--no-interaction', '--quiet'], stage);

mkdirSync(target, { recursive: true });
run('rsync', ['-a', '--exclude-from=.distignore', '--exclude=dist', '--exclude=/vendor', './', `dist/${slug}/`]);
run('rsync', ['-a', `--exclude-from=${resolve(root, '.distignore')}`, './', `${target}/`], stage);
rmSync(stage, { recursive: true, force: true });

for (const file of required) {
  if (!existsSync(resolve(target, file))) {
    console.error(`Missing ${file} in the package`);
    process.exit(1);
  }
}

run('zip', ['-qr', `${slug}.zip`, slug], dist);
rmSync(target, { recursive: true, force: true });

const version = JSON.parse(readFileSync(resolve(root, 'package.json'), 'utf8')).version;
const size = (statSync(zipPath).size / 1024).toFixed(0);
console.log(`\nPackage ready: ${zipPath}`);
console.log(`Version ${version}, ${size} KB, sha256 ${await sha256(zipPath)}`);
console.log('Install: WP admin > Plugins > Add New > Upload Plugin, or replace wp-content/plugins/' + slug);
