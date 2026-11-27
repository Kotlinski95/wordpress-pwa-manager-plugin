<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

define('ABSPATH', sys_get_temp_dir() . '/wp-pwa-manager-tests/');
define('DAY_IN_SECONDS', 86400);
define('WP_PWA_MANAGER_VERSION', '0.0.0-test');
define('WP_PWA_MANAGER_PLUGIN_URL', 'https://example.test/wp-content/plugins/wordpress-pwa-manager/');
define('WP_PWA_MANAGER_PLUGIN_PATH', dirname(__DIR__, 2) . '/');
define('WP_PWA_MANAGER_PLUGIN_FILE', dirname(__DIR__, 2) . '/wordpress-pwa-manager.php');

if (!is_dir(ABSPATH)) {
    mkdir(ABSPATH, 0777, true);
}

require_once WP_PWA_MANAGER_PLUGIN_PATH . 'includes/pwa-settings.php';
require_once WP_PWA_MANAGER_PLUGIN_PATH . 'includes/pwa-manifest.php';
require_once WP_PWA_MANAGER_PLUGIN_PATH . 'includes/pwa-service-worker.php';
require_once WP_PWA_MANAGER_PLUGIN_PATH . 'includes/pwa-updater.php';
require_once WP_PWA_MANAGER_PLUGIN_PATH . 'includes/pwa-frontend.php';
