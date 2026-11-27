import { readFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

const SEMVER = /^\d+\.\d+\.\d+(-(beta|rc)\.\d+)?$/;
const PARTS = /^(\d+)\.(\d+)\.(\d+)(?:-(beta|rc)\.(\d+))?$/;
const PRE_ORDER = { beta: 0, rc: 1 };
const HEADER_PATTERN = /^(\s*\*\s*Version:\s*)(\S+)/m;
const CONSTANT_PATTERN = /(define\(\s*'WP_PWA_MANAGER_VERSION',\s*')([^']+)(')/;
const AUTHOR_PATTERN = /^\s*\*\s*Author:\s*(.+?)\s*$/m;
const CHANGELOG_HEADING = /^## \[(\d+\.\d+\.\d+(?:-(?:beta|rc)\.\d+)?)\]/m;

export const files = (root) => ({
  plugin: join(root, 'wordpress-pwa-manager.php'),
  pkg: join(root, 'package.json'),
  lock: join(root, 'package-lock.json'),
  changelog: join(root, 'CHANGELOG.md')
});

export const readVersions = (root, tag = '') => {
  const paths = files(root);
  const plugin = readFileSync(paths.plugin, 'utf8');
  const changelog = readFileSync(paths.changelog, 'utf8');
  const versions = {
    'plugin header': plugin.match(HEADER_PATTERN)?.[2],
    'package.json': JSON.parse(readFileSync(paths.pkg, 'utf8')).version,
    'CHANGELOG heading': changelog.match(CHANGELOG_HEADING)?.[1]
  };
  const constant = plugin.match(CONSTANT_PATTERN)?.[2];
  if (constant !== undefined) {
    versions['WP_PWA_MANAGER_VERSION'] = constant;
  }
  if (tag.startsWith('v')) {
    versions['git tag'] = tag.slice(1);
  }
  return versions;
};

export const readAuthors = (root) => {
  const paths = files(root);
  const plugin = readFileSync(paths.plugin, 'utf8');
  return {
    'plugin header': plugin.match(AUTHOR_PATTERN)?.[1],
    'package.json': JSON.parse(readFileSync(paths.pkg, 'utf8')).author
  };
};

export const findAuthorMismatch = (authors) => {
  const entries = Object.entries(authors);
  const missing = entries.filter(([, value]) => typeof value !== 'string' || value.trim() === '');
  if (missing.length > 0) {
    return `Missing author in: ${missing.map(([name]) => name).join(', ')}`;
  }
  if (new Set(entries.map(([, value]) => value)).size > 1) {
    return `Author mismatch: ${entries.map(([name, value]) => `${name}=${value}`).join(', ')}`;
  }
  return null;
};

export const findMismatch = (versions) => {
  const entries = Object.entries(versions);
  const missing = entries.filter(([, value]) => !value || !SEMVER.test(value));
  if (missing.length > 0) {
    return `Missing or invalid version in: ${missing.map(([name]) => name).join(', ')}`;
  }
  if (new Set(entries.map(([, value]) => value)).size > 1) {
    return `Version mismatch: ${entries.map(([name, value]) => `${name}=${value}`).join(', ')}`;
  }
  return null;
};

export const parseVersion = (version) => {
  const match = PARTS.exec(version);
  if (!match) {
    throw new Error(`Not a release version: ${version}`);
  }
  const [, major, minor, patch, label, number] = match;
  return { major: Number(major), minor: Number(minor), patch: Number(patch), label: label ?? null, number: number === undefined ? null : Number(number) };
};

const baseOf = ({ major, minor, patch }) => `${major}.${minor}.${patch}`;

export const bumpVersion = (current, level, pre = null) => {
  const parts = parseVersion(current);
  if (pre !== null && !Object.hasOwn(PRE_ORDER, pre)) {
    throw new Error(`Unknown prerelease label: ${pre}`);
  }
  if (parts.label !== null) {
    if (pre === null) {
      return baseOf(parts);
    }
    if (pre === parts.label) {
      return `${baseOf(parts)}-${pre}.${parts.number + 1}`;
    }
    if (PRE_ORDER[pre] < PRE_ORDER[parts.label]) {
      throw new Error(`Cannot go from ${parts.label} back to ${pre}`);
    }
    return `${baseOf(parts)}-${pre}.1`;
  }
  const { major, minor, patch } = parts;
  let next = `${major}.${minor}.${patch + 1}`;
  if (level === 'major') {
    next = `${major + 1}.0.0`;
  } else if (level === 'minor') {
    next = `${major}.${minor + 1}.0`;
  }
  return pre === null ? next : `${next}-${pre}.1`;
};

export const isPromotion = (current, next) => {
  const from = parseVersion(current);
  return from.label !== null && baseOf(from) === next;
};

export const extractReleaseNotes = (changelog, version) => {
  if (!SEMVER.test(version)) {
    throw new Error(`Not a release version: ${version}`);
  }
  const marker = `## [${version}]`;
  const lines = changelog.split('\n');
  const start = lines.findIndex((line) => line.startsWith(marker));
  if (start === -1) {
    throw new Error(`CHANGELOG.md has no section for ${version}`);
  }
  const rest = lines.slice(start + 1);
  const end = rest.findIndex((line) => line.startsWith('## ['));
  const body = (end === -1 ? rest : rest.slice(0, end)).join('\n').trim();
  if (body === '') {
    throw new Error(`CHANGELOG.md section for ${version} is empty`);
  }
  return body;
};

export const releaseChangelog = (changelog, version, date, emptyFallback = null) => {
  const marker = '## [Unreleased]';
  const start = changelog.indexOf(marker);
  if (start === -1) {
    throw new Error('CHANGELOG.md has no [Unreleased] section');
  }
  const bodyStart = start + marker.length;
  const rest = changelog.slice(bodyStart);
  const nextHeading = rest.search(/^## \[/m);
  const body = nextHeading === -1 ? rest : rest.slice(0, nextHeading);
  const tail = nextHeading === -1 ? '' : rest.slice(nextHeading);
  const text = body.trim() === '' && emptyFallback !== null ? emptyFallback : body.trim();
  if (text === '') {
    throw new Error('[Unreleased] section is empty');
  }
  return `${changelog.slice(0, start)}${marker}\n\n## [${version}] - ${date}\n\n${text}\n\n${tail}`.replace(/\n+$/, '\n');
};

export const applyVersion = (root, version, date, emptyFallback = null) => {
  const paths = files(root);
  const plugin = readFileSync(paths.plugin, 'utf8')
    .replace(HEADER_PATTERN, `$1${version}`)
    .replace(CONSTANT_PATTERN, `$1${version}$3`);
  const pkg = JSON.parse(readFileSync(paths.pkg, 'utf8'));
  pkg.version = version;
  const lock = JSON.parse(readFileSync(paths.lock, 'utf8'));
  lock.version = version;
  if (lock.packages?.['']) {
    lock.packages[''].version = version;
  }
  const changelog = releaseChangelog(readFileSync(paths.changelog, 'utf8'), version, date, emptyFallback);
  writeFileSync(paths.plugin, plugin);
  writeFileSync(paths.pkg, `${JSON.stringify(pkg, null, 2)}\n`);
  writeFileSync(paths.lock, `${JSON.stringify(lock, null, 2)}\n`);
  writeFileSync(paths.changelog, changelog);
};
