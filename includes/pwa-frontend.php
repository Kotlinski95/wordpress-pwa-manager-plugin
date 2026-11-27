<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function wp_pwa_manager_frontend_init() {
    add_action('wp_footer', 'wp_pwa_manager_add_pwa_scripts');
    add_action('wp_head', 'wp_pwa_manager_print_install_gate_capture', 1);
    wp_pwa_manager_handle_offline_page();
}

function wp_pwa_manager_get_install_gate_config() {
    $config = apply_filters('wp_pwa_manager_install_gate', array(
        'min_views' => 0,
        'min_visible_ms' => 0,
        'consent_cookie' => '',
    ));
    $config = is_array($config) ? $config : array();

    return array(
        'min_views' => max(0, (int) ($config['min_views'] ?? 0)),
        'min_visible_ms' => max(0, (int) ($config['min_visible_ms'] ?? 0)),
        'consent_cookie' => preg_replace('/[^A-Za-z0-9_\-]/', '', (string) ($config['consent_cookie'] ?? '')),
    );
}

function wp_pwa_manager_install_gate_enabled() {
    $config = wp_pwa_manager_get_install_gate_config();

    return $config['min_views'] > 0 || $config['min_visible_ms'] > 0 || '' !== $config['consent_cookie'];
}

function wp_pwa_manager_get_install_gate_capture_script() {
    return '(function(w){var s=w.wpPwaEarlyPrompt={event:null,listener:function(e){e.preventDefault();e.stopImmediatePropagation();s.event=e;}};w.addEventListener("beforeinstallprompt",s.listener,true);})(window);';
}

function wp_pwa_manager_print_install_gate_capture() {
    if (!wp_pwa_manager_get_setting('install_prompt_enabled', true) || !wp_pwa_manager_install_gate_enabled()) {
        return;
    }

    wp_print_inline_script_tag(wp_pwa_manager_get_install_gate_capture_script());
}

function wp_pwa_manager_get_frontend_data() {
    return array(
        'settings' => wp_pwa_manager_get_public_settings(),
        'installGate' => wp_pwa_manager_get_install_gate_config(),
        'swUrl' => home_url('/sw.js'),
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'i18n' => array(
            'installPromptTitle' => __('Install App', 'wordpress-pwa-manager'),
            'installPromptText' => __('Install this app on your device for a better experience and quick access.', 'wordpress-pwa-manager'),
            'installPromptInstall' => __('Install', 'wordpress-pwa-manager'),
            'installPromptLater' => __('Ask me later', 'wordpress-pwa-manager'),
            'installPromptDismiss' => __('No thanks', 'wordpress-pwa-manager'),
            'updatePromptTitle' => __('Update Available', 'wordpress-pwa-manager'),
            'updatePromptText' => __('A new version of this app is available. Update now for the latest features and improvements.', 'wordpress-pwa-manager'),
            'updatePromptUpdate' => __('Update Now', 'wordpress-pwa-manager'),
            'updatePromptLater' => __('Later', 'wordpress-pwa-manager'),
            'connectionOnline' => __('Back online', 'wordpress-pwa-manager'),
            'connectionOffline' => __('You are offline', 'wordpress-pwa-manager'),
        ),
    );
}

function wp_pwa_manager_handle_offline_page() {
    add_rewrite_rule('^offline/?$', 'index.php?wp_pwa_offline=1', 'top');
    add_filter('query_vars', 'wp_pwa_manager_add_query_vars');
    add_action('template_redirect', 'wp_pwa_manager_serve_offline_page');
}

function wp_pwa_manager_add_query_vars($vars) {
    $vars[] = 'wp_pwa_offline';
    return $vars;
}

function wp_pwa_manager_serve_offline_page() {
    if (get_query_var('wp_pwa_offline')) {
        wp_pwa_manager_display_offline_page();
        exit;
    }
}

function wp_pwa_manager_display_offline_page() {
    $settings = wp_pwa_manager_get_all_settings();
    get_header();
    ?>
    <div class="wp-pwa-offline-page">
        <div class="wp-pwa-offline-content">
            <h1><?php echo esc_html($settings['offline_page_title']); ?></h1>
            <p><?php echo esc_html($settings['offline_page_message']); ?></p>
            <button onclick="window.location.reload();" class="wp-pwa-retry-button">
                <?php esc_html_e('Try Again', 'wordpress-pwa-manager'); ?>
            </button>
        </div>
    </div>
    <style>
    .wp-pwa-offline-page {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 50vh;
        text-align: center;
        padding: 2rem;
    }
    .wp-pwa-offline-content h1 {
        color: <?php echo esc_attr($settings['theme_color']); ?>;
        margin-bottom: 1rem;
    }
    .wp-pwa-retry-button {
        background: <?php echo esc_attr($settings['theme_color']); ?>;
        color: <?php echo esc_attr($settings['background_color']); ?>;
        border: none;
        padding: 0.75rem 1.5rem;
        border-radius: 0.25rem;
        cursor: pointer;
        font-size: 1rem;
        margin-top: 1rem;
    }
    .wp-pwa-retry-button:hover {
        opacity: 0.8;
    }
    </style>
    <?php
    get_footer();
}

function wp_pwa_manager_add_pwa_scripts() {
    // Service worker registration is handled by the compiled frontend.js file
    // which includes additional PWA features like install prompts, update handling, etc.
}

function wp_pwa_manager_make_stylesheet_non_blocking($html, $handle) {
    if ('wp-pwa-manager-frontend' !== $handle) {
        return $html;
    }

    $non_blocking = str_replace('<link', '<link data-no-defer="1" data-no-optimize="1"', $html);
    $non_blocking = str_replace(array('media="all"', "media='all'"), array('media="print"', "media='print'"), $non_blocking);

    if (false === strpos($non_blocking, 'onload=')) {
        $non_blocking = str_replace(
            array("media='print'", 'media="print"'),
            array("media='print' onload=\"this.media='all'\"", 'media="print" onload="this.media=\'all\'"'),
            $non_blocking
        );
    }

    return $non_blocking . '<noscript>' . $html . '</noscript>' . "\n";
}
