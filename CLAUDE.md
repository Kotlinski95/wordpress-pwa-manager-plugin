# wordpress-pwa-manager

Standalone WordPress PWA plugin: manifest, generated service worker, install and update prompts, offline page, React admin screen.

## Stack

- PHP 8.1+, procedural functions prefixed `wp_pwa_manager_`, files in `includes/`
- TypeScript and React in `src/`, built with `@wordpress/scripts` into `build/`
- Update checks through `yahnis-elsts/plugin-update-checker`, wired in `includes/pwa-updater.php`

## Verify

```bash
npm run lint && npm test && npm run build && vendor/bin/phpunit && npm run check-version
```

## Conventions

- No comments in new code, including docblocks. The plugin header block in `wordpress-pwa-manager.php` is the only exception.
- Text domain `wordpress-pwa-manager`. Option names start with `wp_pwa_`.
- Frontend assets use handle `wp-pwa-manager-frontend`. The script is deferred and the stylesheet loads non-blocking.
- jQuery is not available in frontend scripts.
- Never commit or push unless asked. Never add AI attribution lines to commits, tags or PRs.

## Changelog

- Log every notable change under `[Unreleased]` in `CHANGELOG.md` (Keep a Changelog format).
- Keep each entry to 2-3 sentences.

## Release

- Version files kept in sync: `wordpress-pwa-manager.php` (header and `WP_PWA_MANAGER_VERSION`), `package.json`, `package-lock.json`, `CHANGELOG.md`.
- Run `npm run release` (`--dry-run` previews, `--minor` and `--major` override), then `npm run check-version`.
- Commit `chore: release vX.Y.Z`, tag `vX.Y.Z` and push. The tag runs the release workflow, which attaches `wordpress-pwa-manager.zip` and its `.sha256`.
- Update slug `wordpress-pwa-manager`; release asset name `wordpress-pwa-manager.zip`.
