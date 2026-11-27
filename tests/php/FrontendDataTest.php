<?php
declare(strict_types=1);

namespace Kotlinskidev\PwaManager\Tests;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;

final class FrontendDataTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Functions\when('admin_url')->alias(static fn(string $path = ''): string => 'https://example.test/wp-admin/' . $path);
    }

    public function test_public_settings_expose_only_what_the_frontend_needs(): void
    {
        $this->options['wp_pwa_vapid_private_key'] = 'secret-private-key';
        $this->options['wp_pwa_vapid_public_key'] = 'public-key';

        $settings = wp_pwa_manager_get_public_settings();

        $keys = array_keys($settings);
        sort($keys);
        $this->assertSame(
            array('install_prompt_enabled', 'push_notifications_enabled', 'update_prompt_button_text', 'update_prompt_enabled', 'update_prompt_text', 'vapid_public_key'),
            $keys
        );
        $this->assertSame('public-key', $settings['vapid_public_key']);
        $this->assertStringNotContainsString('secret-private-key', (string) json_encode($settings));
    }

    public function test_frontend_data_has_no_nonce_and_no_private_settings(): void
    {
        $data = wp_pwa_manager_get_frontend_data();

        $this->assertArrayNotHasKey('nonce', $data);
        $this->assertArrayNotHasKey('vapid_private_key', $data['settings']);
        $this->assertArrayNotHasKey('app_name', $data['settings']);
        $this->assertSame('https://example.test/sw.js', $data['swUrl']);
        $this->assertArrayHasKey('installGate', $data);
        $this->assertArrayHasKey('connectionOffline', $data['i18n']);
    }

    public function test_install_gate_is_disabled_by_default(): void
    {
        $this->assertSame(
            array('min_views' => 0, 'min_visible_ms' => 0, 'consent_cookie' => ''),
            wp_pwa_manager_get_install_gate_config()
        );
        $this->assertFalse(wp_pwa_manager_install_gate_enabled());
    }

    public function test_install_gate_filter_sets_the_conditions(): void
    {
        Filters\expectApplied('wp_pwa_manager_install_gate')->andReturn(array(
            'min_views' => 2,
            'min_visible_ms' => 30000,
            'consent_cookie' => 'cmplz_banner-status',
        ));

        $this->assertSame(
            array('min_views' => 2, 'min_visible_ms' => 30000, 'consent_cookie' => 'cmplz_banner-status'),
            wp_pwa_manager_get_install_gate_config()
        );
    }

    public function test_install_gate_filter_output_is_sanitized(): void
    {
        Filters\expectApplied('wp_pwa_manager_install_gate')->andReturn(array(
            'min_views' => -4,
            'min_visible_ms' => '1500',
            'consent_cookie' => 'a b;<script>"x',
        ));

        $this->assertSame(
            array('min_views' => 0, 'min_visible_ms' => 1500, 'consent_cookie' => 'abscriptx'),
            wp_pwa_manager_get_install_gate_config()
        );
    }

    public function test_install_gate_filter_returning_non_array_falls_back_to_disabled(): void
    {
        Filters\expectApplied('wp_pwa_manager_install_gate')->andReturn('nope');

        $this->assertFalse(wp_pwa_manager_install_gate_enabled());
    }

    public function test_capture_script_is_printed_only_when_the_gate_is_enabled(): void
    {
        Functions\expect('wp_print_inline_script_tag')->never();
        wp_pwa_manager_print_install_gate_capture();
        $this->addToAssertionCount(1);
    }

    public function test_capture_script_is_printed_when_gate_enabled(): void
    {
        Filters\expectApplied('wp_pwa_manager_install_gate')->andReturn(array('min_views' => 1));
        Functions\expect('wp_print_inline_script_tag')->once()->with(wp_pwa_manager_get_install_gate_capture_script());

        wp_pwa_manager_print_install_gate_capture();
        $this->addToAssertionCount(1);
    }

    public function test_capture_script_is_skipped_when_install_prompt_is_disabled(): void
    {
        $this->options['wp_pwa_install_prompt_enabled'] = false;
        Filters\expectApplied('wp_pwa_manager_install_gate')->andReturn(array('min_views' => 1));
        Functions\expect('wp_print_inline_script_tag')->never();

        wp_pwa_manager_print_install_gate_capture();
        $this->addToAssertionCount(1);
    }

    public function test_capture_script_registers_a_capturing_listener(): void
    {
        $script = wp_pwa_manager_get_install_gate_capture_script();

        $this->assertStringContainsString('wpPwaEarlyPrompt', $script);
        $this->assertStringContainsString('beforeinstallprompt', $script);
        $this->assertStringContainsString('preventDefault', $script);
    }
}
