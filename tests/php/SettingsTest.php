<?php
declare(strict_types=1);

namespace Kotlinskidev\PwaManager\Tests;

use Brain\Monkey\Functions;

final class SettingsTest extends TestCase
{
    public function test_defaults_use_site_identity(): void
    {
        $settings = wp_pwa_manager_get_all_settings();

        $this->assertSame('Test Site', $settings['app_name']);
        $this->assertSame('Test Tagline', $settings['app_description']);
        $this->assertSame('standalone', $settings['display']);
        $this->assertSame('cache_first', $settings['cache_strategy']);
    }

    public function test_stored_options_override_defaults(): void
    {
        $this->options['wp_pwa_theme_color'] = '#123456';

        $this->assertSame('#123456', wp_pwa_manager_get_setting('theme_color'));
    }

    public function test_cache_limits_are_cast_to_integers(): void
    {
        $this->options['wp_pwa_cache_max_entries'] = '75';
        $this->options['wp_pwa_cache_max_age'] = '7';

        $settings = wp_pwa_manager_get_all_settings();

        $this->assertSame(75, $settings['cache_max_entries']);
        $this->assertSame(7, $settings['cache_max_age']);
    }

    public function test_unknown_setting_returns_the_given_default(): void
    {
        $this->assertSame('fallback', wp_pwa_manager_get_setting('missing', 'fallback'));
    }

    public function test_update_setting_prefixes_the_option_name(): void
    {
        Functions\expect('update_option')->once()->with('wp_pwa_theme_color', '#abcdef')->andReturn(true);

        $this->assertTrue(wp_pwa_manager_update_setting('theme_color', '#abcdef'));
    }

    public function test_default_options_only_fill_missing_ones(): void
    {
        $this->options['wp_pwa_display'] = 'fullscreen';
        $added = array();
        Functions\when('get_option')->alias(fn(string $name, $default = false) => $this->options[$name] ?? false);
        Functions\when('add_option')->alias(function (string $name, $value) use (&$added): bool {
            $added[$name] = $value;
            return true;
        });

        wp_pwa_manager_set_default_options();

        $this->assertArrayNotHasKey('wp_pwa_display', $added);
        $this->assertSame('cache_first', $added['wp_pwa_cache_strategy']);
    }

    public function test_offline_template_placeholders_are_replaced(): void
    {
        $settings = array(
            'offline_page_title' => 'Offline',
            'offline_page_message' => 'Retry later',
            'app_name' => 'App',
            'theme_color' => '#000000',
            'background_color' => '#ffffff',
        );

        $output = wp_pwa_manager_process_offline_template('{{OFFLINE_TITLE}}|{{OFFLINE_MESSAGE}}|{{APP_NAME}}|{{HOME_URL}}|{{SITE_NAME}}', $settings);

        $this->assertSame('Offline|Retry later|App|https://example.test/|Test Site', $output);
    }

    public function test_theme_template_falls_back_to_default_when_file_is_missing(): void
    {
        Functions\when('get_template_directory')->justReturn(sys_get_temp_dir() . '/wp-pwa-manager-no-theme');
        $this->options['wp_pwa_offline_page_use_custom_template'] = true;
        $this->options['wp_pwa_offline_page_template_source'] = 'theme';

        $this->assertFalse(wp_pwa_manager_theme_offline_template_exists());
        $this->assertSame(wp_pwa_manager_get_default_offline_template(), wp_pwa_manager_get_offline_template());
    }

    public function test_plugin_template_falls_back_to_default_when_empty(): void
    {
        $this->options['wp_pwa_offline_page_use_custom_template'] = true;
        $this->options['wp_pwa_offline_page_template_source'] = 'plugin';

        $this->assertSame(wp_pwa_manager_get_default_offline_template(), wp_pwa_manager_get_offline_template());
    }
}
