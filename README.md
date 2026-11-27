# WordPress PWA Manager

A comprehensive Progressive Web App (PWA) plugin for WordPress with complete admin control over all PWA features.

## Features

- **Complete PWA Functionality**: Transform your WordPress site into a full-featured PWA
- **Admin Interface**: Beautiful React/TypeScript admin interface for managing all settings
- **Customizable Colors**: Theme and background colors with live preview
- **Icon Management**: Upload and manage PWA icons (192x192, 512x512)
- **Install Prompts**: Configurable app installation prompts
- **Update Notifications**: Automatic update prompts for new app versions
- **Advanced Caching**: Multiple caching strategies (Cache First, Network First, Stale While Revalidate)
- **Offline Support**: Custom offline pages and offline detection
- **Push Notifications**: Full push notification support with VAPID keys
- **Service Worker**: Automatically generated service worker with advanced features
- **Web App Manifest**: Dynamic manifest generation
- **Responsive Design**: Works perfectly on all devices
- **Accessibility**: Full accessibility support with ARIA labels
- **Dark Mode**: Automatic dark mode support
- **High Contrast**: Support for high contrast mode
- **Reduced Motion**: Respects user's reduced motion preferences

## Installation

Download `wordpress-pwa-manager.zip` from the latest [GitHub release](https://github.com/Kotlinski95/wordpress-pwa-manager-plugin/releases) and upload it under Plugins > Add New > Upload Plugin. Activate it, then open Settings > PWA Manager.

Requires WordPress 6.4+ and PHP 8.1+.

## Updates

New releases appear as a normal plugin update in the WordPress admin, served from GitHub release assets. To turn the update checks off, for example when the site deploys another way, add this to `wp-config.php`:

```php
define( 'WP_PWA_MANAGER_DISABLE_UPDATER', true );
```

The `wp_pwa_manager_enable_updater` filter does the same: return `false` to disable.

Every release zip is signed with Ed25519. The release workflow signs it with the `RELEASE_SIGNING_KEY` secret (a PEM private key) through `bin/sign-release.mjs` and attaches `wordpress-pwa-manager.zip.sig` next to the zip and its `.sha256`. Before WordPress installs an update, the plugin checks the package URL against `https://github.com/Kotlinski95/wordpress-pwa-manager-plugin/releases/download/vX.Y.Z/wordpress-pwa-manager.zip`, fetches the `.sig` file and verifies it against the public key in `WP_PWA_MANAGER_UPDATE_PUBLIC_KEY`. A missing or invalid signature aborts the update.

## Theme integration

The frontend script (handle `wp-pwa-manager-frontend`, loaded with the `defer` strategy) injects markup a theme can style. The stylesheet of the same handle loads non-blocking, so a theme should not depend on it for first paint.

| Class | Element |
| --- | --- |
| `.pwa-install-prompt` | Install prompt wrapper, gets `.show` when visible |
| `.pwa-update-prompt` | Update prompt wrapper, gets `.show` when visible |
| `.pwa-prompt-content`, `.pwa-prompt-icon`, `.pwa-prompt-text`, `.pwa-prompt-buttons` | Inner parts of both prompts (`.pwa-prompt-*`) |
| `.pwa-install-button` | Install action button |
| `.pwa-connection-status` | Online and offline toast, with `.pwa-status-online` or `.pwa-status-offline` |

A theme can ship its own `offline.html` in its root and pick it under the offline settings. Without that file the plugin falls back to its built-in offline page.

### Styling API

Prompt, button and connection-status styles read `--wp-pwa-*` custom properties. Each one defaults to a WordPress preset variable (for example `var(--wp--preset--color--primary)`) and then to a built-in fallback, so a block theme is styled with no extra CSS. Set them on `:root` or any ancestor to override.

| Property | Default |
| --- | --- |
| `--wp-pwa-surface` | `--wp--preset--color--surface`, `#fff` |
| `--wp-pwa-foreground` | `--wp--preset--color--foreground`, `#333` |
| `--wp-pwa-divider` | `--wp--preset--color--divider`, `#e0e0e0` |
| `--wp-pwa-accent`, `--wp-pwa-accent-contrast` | `--wp--preset--color--primary`, `#0073aa`; `--wp--preset--color--white`, `#fff` |
| `--wp-pwa-update-accent` | `--wp--preset--color--secondary`, `#f56500` |
| `--wp-pwa-muted`, `--wp-pwa-muted-foreground` | `--wp--preset--color--surface-contrast`, `#f0f0f0`; `--wp--preset--color--foreground-contrast`, `#666` |
| `--wp-pwa-online-bg`, `-border`, `-color` | green toast colors |
| `--wp-pwa-offline-bg`, `-border`, `-color` | red toast colors |
| `--wp-pwa-radius`, `--wp-pwa-button-radius`, `--wp-pwa-shadow` | `0.75rem`, `0.375rem`, soft shadow |
| `--wp-pwa-z-index` | `10000` (the status toast sits one above) |
| `--wp-pwa-inset-bottom`, `--wp-pwa-inset-inline` | `1.25rem` |
| `--wp-pwa-status-top`, `--wp-pwa-status-inset-inline` | `1.25rem`, `50%` |

Transitions and the update icon spin are switched off under `prefers-reduced-motion: reduce` and under `html.ka11y-stop-animations`.

### Install gate

Chrome fires `beforeinstallprompt` once and early. The plugin can hold that event and release it to the install prompt only after the visitor has engaged. It is off until the `wp_pwa_manager_install_gate` filter sets at least one condition:

```php
add_filter( 'wp_pwa_manager_install_gate', static fn( array $gate ): array => array(
	'min_views'      => 2,
	'min_visible_ms' => 30000,
	'consent_cookie' => 'cmplz_banner-status',
) );
```

| Key | Meaning | Default |
| --- | --- | --- |
| `min_views` | Page views needed (counted in `localStorage` key `wp-pwa-manager-views`) | `0` |
| `min_visible_ms` | Visible time needed (`wp-pwa-manager-visible-ms`); either this or `min_views` is enough | `0` |
| `consent_cookie` | Cookie that must exist before the prompt shows, such as a consent banner status | `''` (not required) |

When the gate is on, a tiny inline script in `<head>` captures the event before the deferred bundle loads. The bundle then replays a clone with `ktReplayed: true` once the conditions hold, and the native `prompt()` still works through it.

### Theme and plugin contract

- The plugin owns the markup, the base styles, the install gate and reduced-motion handling. A theme should not restyle `.pwa-*` selectors or re-implement the gate.
- A theme sets `--wp-pwa-*` properties, and calls the `wp_pwa_manager_install_gate` filter to choose its engagement and consent rules.
- Only the settings the frontend needs are sent to the page (`wpPwaManager.settings`). No nonce and no private keys are printed on public pages.

## Development

```bash
npm ci && composer install
npm run build
npm test
vendor/bin/phpunit
npm run package
```

## Configuration

### General Settings
- App name and description
- Start URL and scope
- Display mode and orientation

### Appearance
- Theme color and background color
- Live mobile preview

### Icons
- Upload PWA icons with drag-and-drop
- Automatic validation and optimization

### Install Prompts
- Customizable installation prompts
- Update notification settings

### Caching
- Choose from multiple caching strategies
- Configure cache limits and duration
- Custom offline pages

### Push Notifications
- VAPID key management
- Permission handling
- Test notifications

## Technical Details

- Built with TypeScript and React
- Uses @wordpress/scripts for building
- Service Worker with advanced caching
- REST API for settings management
- Follows WordPress coding standards

## Browser Support

- Chrome 67+
- Firefox 68+
- Safari 13+
- Edge 79+

## License

GPL v2 or later
