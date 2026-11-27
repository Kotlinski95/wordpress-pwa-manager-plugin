<?php

if (!defined('ABSPATH')) {
    exit;
}

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

function wp_pwa_manager_updater_enabled() {
    if (defined('WP_PWA_MANAGER_DISABLE_UPDATER') && true === constant('WP_PWA_MANAGER_DISABLE_UPDATER')) {
        return false;
    }

    return (bool) apply_filters('wp_pwa_manager_enable_updater', true);
}

function wp_pwa_manager_updater_init($plugin_file) {
    if (!wp_pwa_manager_updater_enabled() || !class_exists(PucFactory::class)) {
        return null;
    }

    $checker = PucFactory::buildUpdateChecker(
        'https://github.com/Kotlinski95/wordpress-pwa-manager-plugin/',
        $plugin_file,
        'wordpress-pwa-manager'
    );
    $checker->setBranch('main');
    $checker->getVcsApi()->enableReleaseAssets('/wordpress-pwa-manager\.zip/');

    return $checker;
}
