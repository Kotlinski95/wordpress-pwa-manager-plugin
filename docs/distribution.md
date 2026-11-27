# Distributing the plugin to client sites

The plugin lives in its own repository and is released independently of the theme. Client sites install it once from a zip and then receive updates from GitHub releases.

## Flow

1. Add changelog entries under `[Unreleased]` and run `npm run release` (`--patch`, `--minor` or `--major`). It bumps the plugin header, `WP_PWA_MANAGER_VERSION`, `package.json` and `package-lock.json` together.
2. Commit, tag `vX.Y.Z` and push with `git push --follow-tags`.
3. The `Release` workflow builds the assets, runs `composer install --no-dev -o`, generates translations, packages `wordpress-pwa-manager.zip`, writes `wordpress-pwa-manager.zip.sha256` and signs the zip into `wordpress-pwa-manager.zip.sig`. It publishes all three as one GitHub release.
4. Client sites see a normal plugin update. Before installing, the plugin validates the package URL and verifies the Ed25519 signature. A missing or invalid signature aborts the update.

## Packaging

`npm run package` builds the same zip locally into `dist/`. File selection comes from `.distignore`. `.git`, `node_modules`, tests, sources and workflow files never ship. `vendor/` and `build/` ship.

## Signing keys

- The private key is the repository secret `RELEASE_SIGNING_KEY` (Ed25519, PKCS8 PEM). Without it the release job fails on purpose.
- The public key is the constant `WP_PWA_MANAGER_UPDATE_PUBLIC_KEY` in `includes/pwa-updater.php`.
- To rotate, release a version signed with the old key that carries the new public key, then switch the secret.

## Client sites

- First install: upload `wordpress-pwa-manager.zip` under Plugins > Add New > Upload Plugin. To replace an older copy, choose Replace current.
- Copies older than the first signed release have no update checker and need one manual upload.
- Updates are turned off with `define( 'WP_PWA_MANAGER_DISABLE_UPDATER', true );` or the `wp_pwa_manager_enable_updater` filter.
- Rollback is manual: upload an older zip from the releases page.
