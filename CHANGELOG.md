# Changelog

All notable changes to this project are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added

- The plugin now updates itself from GitHub releases through Plugin Update Checker. Set `WP_PWA_MANAGER_DISABLE_UPDATER` or use the `wp_pwa_manager_enable_updater` filter to turn it off.
- Standalone repository tooling: Composer, release scripts, PHP and Jest unit tests, and CI, CodeQL and release workflows.

### Changed

- The frontend script loads with the `defer` strategy and the frontend stylesheet loads non-blocking with a `noscript` fallback.
- Plugin headers declare WordPress 6.4, PHP 8.1, the text domain path and the update URI.

## [1.0.1] - 2026-01-01

### Fixed

- A new service worker now activates only after the visitor confirms the update prompt.
