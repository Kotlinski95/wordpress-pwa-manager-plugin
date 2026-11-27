<?php
declare(strict_types=1);

namespace Kotlinskidev\PwaManager\Tests;

final class ManifestTest extends TestCase
{
    public function test_manifest_carries_the_core_fields(): void
    {
        $this->options['wp_pwa_theme_color'] = '#112233';
        $this->options['wp_pwa_start_url'] = '/app/';

        $manifest = wp_pwa_manager_generate_manifest();

        $this->assertSame('Test Site', $manifest['name']);
        $this->assertSame('/app/', $manifest['start_url']);
        $this->assertSame('#112233', $manifest['theme_color']);
        $this->assertSame('standalone', $manifest['display']);
    }

    public function test_manifest_falls_back_to_bundled_icons(): void
    {
        $icons = wp_pwa_manager_generate_manifest()['icons'];

        $this->assertSame('192x192', $icons[0]['sizes']);
        $this->assertSame(WP_PWA_MANAGER_PLUGIN_URL . 'assets/icons/icon-192x192.png', $icons[0]['src']);
        $this->assertSame(WP_PWA_MANAGER_PLUGIN_URL . 'assets/icons/icon-512x512.png', $icons[1]['src']);
    }

    public function test_manifest_uses_custom_icons_when_set(): void
    {
        $this->options['wp_pwa_icon_192'] = 'https://cdn.test/a.png';

        $this->assertSame('https://cdn.test/a.png', wp_pwa_manager_generate_manifest()['icons'][0]['src']);
    }

    public function test_settings_hash_changes_with_manifest_fields_only(): void
    {
        $base = wp_pwa_manager_manifest_get_settings_hash();

        $this->options['wp_pwa_cache_strategy'] = 'network_first';
        $this->assertSame($base, wp_pwa_manager_manifest_get_settings_hash());

        $this->options['wp_pwa_app_name'] = 'Renamed';
        $this->assertNotSame($base, wp_pwa_manager_manifest_get_settings_hash());
    }

    public function test_head_markup_links_the_manifest_and_escapes_values(): void
    {
        $this->options['wp_pwa_theme_color'] = '#123456"><script>';

        ob_start();
        wp_pwa_manager_add_manifest_link();
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('<link rel="manifest" href="https://example.test/manifest.json">', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_generate_manifest_file_writes_valid_json(): void
    {
        $this->assertTrue(wp_pwa_manager_generate_manifest_file());

        $decoded = json_decode((string) file_get_contents(ABSPATH . 'manifest.json'), true);

        $this->assertSame('Test Site', $decoded['name']);
        unlink(ABSPATH . 'manifest.json');
    }
}
