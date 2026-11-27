import { createPrivateKey, sign } from 'node:crypto';
import { readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

const zipArg = process.argv[2];
const pem = process.env.RELEASE_SIGNING_KEY;

if (!zipArg) {
  console.error('Usage: node bin/sign-release.mjs <zip>');
  process.exit(1);
}

if (!pem) {
  console.error('RELEASE_SIGNING_KEY is not set');
  process.exit(1);
}

const zipPath = resolve(zipArg);
const key = createPrivateKey(pem);

if (key.asymmetricKeyType !== 'ed25519') {
  console.error('RELEASE_SIGNING_KEY must be an Ed25519 private key');
  process.exit(1);
}

const signature = sign(null, readFileSync(zipPath), key);
writeFileSync(`${zipPath}.sig`, signature.toString('base64'));
console.log(`Signed ${zipPath} -> ${zipPath}.sig`);
