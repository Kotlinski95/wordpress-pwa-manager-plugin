<?php
declare(strict_types=1);

namespace Kotlinskidev\PwaManager\Tests;

final class ServiceWorkerTest extends TestCase
{
    private function config(string $script): array
    {
        $this->assertSame(1, preg_match('/^const CONFIG = (\{.*\});$/m', $script, $match));

        return json_decode($match[1], true);
    }

    public function test_script_embeds_config_and_handlers(): void
    {
        $script = wp_pwa_manager_generate_service_worker();

        $this->assertStringContainsString("self.addEventListener('install'", $script);
        $this->assertStringContainsString("self.addEventListener('fetch'", $script);
        $this->assertStringContainsString("'SKIP_WAITING'", $script);
        $this->assertSame('https://example.test/', $this->config($script)['homeUrl']);
    }

    public function test_unknown_cache_strategy_falls_back_to_cache_first(): void
    {
        $this->options['wp_pwa_cache_strategy'] = 'bogus';

        $this->assertSame('cache_first', $this->config(wp_pwa_manager_generate_service_worker())['cacheStrategy']);
    }

    public function test_known_cache_strategy_is_kept(): void
    {
        $this->options['wp_pwa_cache_strategy'] = 'network_first';

        $this->assertSame('network_first', $this->config(wp_pwa_manager_generate_service_worker())['cacheStrategy']);
    }

    public function test_cache_limits_are_clamped_and_converted(): void
    {
        $this->options['wp_pwa_cache_max_entries'] = 0;
        $this->options['wp_pwa_cache_max_age'] = 2;

        $config = $this->config(wp_pwa_manager_generate_service_worker());

        $this->assertSame(1, $config['maxEntries']);
        $this->assertSame(2 * 86400 * 1000, $config['maxAgeMs']);
    }

    public function test_offline_html_is_empty_when_offline_page_is_disabled(): void
    {
        $this->options['wp_pwa_offline_page_enabled'] = false;

        $config = $this->config(wp_pwa_manager_generate_service_worker());

        $this->assertFalse($config['offlineEnabled']);
        $this->assertSame('', $config['offlineHtml']);
    }

    public function test_cache_name_changes_when_settings_change(): void
    {
        $first = $this->config(wp_pwa_manager_generate_service_worker())['cacheName'];
        $this->options['wp_pwa_app_name'] = 'Renamed';
        $second = $this->config(wp_pwa_manager_generate_service_worker())['cacheName'];

        $this->assertNotSame($first, $second);
        $this->assertStringStartsWith('wp-pwa-cache-', $first);
    }

    public function test_generate_file_writes_sw_js(): void
    {
        $this->assertTrue(wp_pwa_manager_generate_service_worker_file());
        $this->assertStringContainsString('const CONFIG', (string) file_get_contents(ABSPATH . 'sw.js'));
        unlink(ABSPATH . 'sw.js');
    }
}
