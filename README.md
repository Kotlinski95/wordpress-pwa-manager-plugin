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
