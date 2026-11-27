# Changelog

All notable changes to this project are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Fixed

- The updater now validates package URLs with `wp_parse_url()` instead of `parse_url()`, which fixes the Plugin Check error.

## [1.2.0] - 2026-11-27

### Added

- Prompt, button and connection-status styles now use `--wp-pwa-*` custom properties that default to WordPress preset variables, and prompt transitions stop under `prefers-reduced-motion` and `html.ka11y-stop-animations`. The styling API is documented in the README.
- An optional install gate holds `beforeinstallprompt` (captured by an early inline script) until `min_views`, `min_visible_ms` and `consent_cookie` from the new `wp_pwa_manager_install_gate` filter are met. It is off by default.
- Releases are signed with Ed25519 (`bin/sign-release.mjs`, `.sig` asset) and the updater verifies the signature against `WP_PWA_MANAGER_UPDATE_PUBLIC_KEY` before installing, rejecting missing or invalid signatures and untrusted package URLs.

### Security

- Public pages no longer print a nonce or the full settings array. Only the prompt and push settings the frontend script uses are localized, which also stops `vapid_private_key` from reaching the page.

### Fixed

- The update checker's release asset pattern is anchored so `.sha256` and `.sig` files are never picked as the package.

## [1.1.0] - 2026-11-27

### Added

- The plugin now updates itself from GitHub releases through Plugin Update Checker. Set `WP_PWA_MANAGER_DISABLE_UPDATER` or use the `wp_pwa_manager_enable_updater` filter to turn it off.
- Standalone repository tooling: Composer, release scripts, PHP and Jest unit tests, and CI, CodeQL and release workflows.

### Fixed

- Saving settings through the REST route no longer fatals; it regenerates the manifest and service worker through their functions.
- Plugin Check errors: every include file now exits on direct access, and the service worker script is built from a template file instead of a heredoc.

### Changed

- The frontend script loads with the `defer` strategy and the frontend stylesheet loads non-blocking with a `noscript` fallback.
- Plugin headers declare WordPress 6.4, PHP 8.1, the text domain path and the update URI.

## [1.0.1] - 2026-01-01

### Fixed

- A new service worker now activates only after the visitor confirms the update prompt.
