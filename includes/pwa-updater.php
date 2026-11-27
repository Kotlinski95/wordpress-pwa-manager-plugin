<?php

if (!defined('ABSPATH')) {
    exit;
}

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

if (!defined('WP_PWA_MANAGER_UPDATE_PUBLIC_KEY')) {
    define('WP_PWA_MANAGER_UPDATE_PUBLIC_KEY', 'YXByqZhv3ZaS74JAzDXEXIpwGjXpBCyj0FpyXxUnnLM=');
}

define('WP_PWA_MANAGER_PACKAGE_PATH_PREFIX', '/Kotlinski95/wordpress-pwa-manager-plugin/');
define('WP_PWA_MANAGER_PACKAGE_PATTERN', '#^/Kotlinski95/wordpress-pwa-manager-plugin/releases/download/v\d+\.\d+\.\d+(?:-[0-9A-Za-z.]+)?/wordpress-pwa-manager\.zip$#');
define('WP_PWA_MANAGER_SIGNATURE_MAX_BYTES', 256);

function wp_pwa_manager_updater_enabled() {
    if (defined('WP_PWA_MANAGER_DISABLE_UPDATER') && true === constant('WP_PWA_MANAGER_DISABLE_UPDATER')) {
        return false;
    }

    return (bool) apply_filters('wp_pwa_manager_enable_updater', true);
}

function wp_pwa_manager_updater_init($plugin_file) {
    add_filter('upgrader_pre_download', 'wp_pwa_manager_verify_package_download', 10, 4);

    if (!wp_pwa_manager_updater_enabled() || !class_exists(PucFactory::class)) {
        return null;
    }

    $checker = PucFactory::buildUpdateChecker(
        'https://github.com/Kotlinski95/wordpress-pwa-manager-plugin/',
        $plugin_file,
        'wordpress-pwa-manager'
    );
    $checker->setBranch('main');
    $checker->getVcsApi()->enableReleaseAssets('/wordpress-pwa-manager\.zip$/');

    return $checker;
}

function wp_pwa_manager_is_own_package($package, $hook_extra) {
    if (isset($hook_extra['plugin']) && is_string($hook_extra['plugin']) && str_starts_with($hook_extra['plugin'], 'wordpress-pwa-manager/')) {
        return true;
    }

    return is_string($package) && str_contains($package, WP_PWA_MANAGER_PACKAGE_PATH_PREFIX);
}

function wp_pwa_manager_validate_package_url($package) {
    if (!is_string($package) || preg_match('/[\x00-\x20\x7f]/', $package) || str_contains($package, chr(92)) || str_contains($package, '..') || str_contains($package, '?') || str_contains($package, '#')) {
        return false;
    }

    $parts = wp_parse_url($package);
    if (!is_array($parts) || array() !== array_diff(array_keys($parts), array('scheme', 'host', 'path'))) {
        return false;
    }

    return 'https' === ($parts['scheme'] ?? '')
        && 'github.com' === ($parts['host'] ?? '')
        && 1 === preg_match(WP_PWA_MANAGER_PACKAGE_PATTERN, $parts['path'] ?? '');
}

function wp_pwa_manager_verify_signature($message, $signature_base64, $public_key_base64 = WP_PWA_MANAGER_UPDATE_PUBLIC_KEY) {
    if (!function_exists('sodium_crypto_sign_verify_detached')) {
        return false;
    }

    $signature = base64_decode(trim((string) $signature_base64), true);
    $public_key = base64_decode((string) $public_key_base64, true);

    if (false === $signature || false === $public_key || SODIUM_CRYPTO_SIGN_BYTES !== strlen($signature) || SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES !== strlen($public_key)) {
        return false;
    }

    return sodium_crypto_sign_verify_detached($signature, (string) $message, $public_key);
}

function wp_pwa_manager_fetch_package_signature($package) {
    $response = wp_safe_remote_get($package . '.sig', array(
        'timeout' => 15,
        'limit_response_size' => WP_PWA_MANAGER_SIGNATURE_MAX_BYTES,
    ));

    if (is_wp_error($response) || 200 !== (int) wp_remote_retrieve_response_code($response)) {
        return '';
    }

    return trim((string) wp_remote_retrieve_body($response));
}

function wp_pwa_manager_verify_package_download($reply, $package, $upgrader = null, $hook_extra = array(), $public_key = WP_PWA_MANAGER_UPDATE_PUBLIC_KEY) {
    if (false !== $reply || !wp_pwa_manager_is_own_package($package, is_array($hook_extra) ? $hook_extra : array())) {
        return $reply;
    }

    if (!wp_pwa_manager_validate_package_url($package)) {
        return new WP_Error('wp_pwa_manager_package_url', __('The update package URL is not trusted.', 'wordpress-pwa-manager'));
    }

    $signature = wp_pwa_manager_fetch_package_signature($package);
    if ('' === $signature) {
        return new WP_Error('wp_pwa_manager_signature_missing', __('The update package has no signature.', 'wordpress-pwa-manager'));
    }

    if (!function_exists('download_url')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    $file = download_url($package);
    if (is_wp_error($file)) {
        return $file;
    }

    $contents = file_get_contents($file);
    if (false === $contents || !wp_pwa_manager_verify_signature($contents, $signature, $public_key)) {
        wp_delete_file($file);
        return new WP_Error('wp_pwa_manager_signature_invalid', __('The update package signature is invalid.', 'wordpress-pwa-manager'));
    }

    return $file;
}
