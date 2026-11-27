<?php
declare(strict_types=1);

namespace Kotlinskidev\PwaManager\Tests;

final class FrontendAssetsTest extends TestCase
{
    private const TAG = "<link rel='stylesheet' id='wp-pwa-manager-frontend-css' href='https://example.test/frontend.css?ver=1' media='all' />\n";

    public function test_other_handles_are_untouched(): void
    {
        $this->assertSame(self::TAG, wp_pwa_manager_make_stylesheet_non_blocking(self::TAG, 'another-handle'));
    }

    public function test_plugin_stylesheet_loads_as_print_then_swaps_to_all(): void
    {
        $html = wp_pwa_manager_make_stylesheet_non_blocking(self::TAG, 'wp-pwa-manager-frontend');

        $this->assertStringContainsString("media='print' onload=\"this.media='all'\"", $html);
        $this->assertStringContainsString('data-no-defer="1"', $html);
    }

    public function test_noscript_fallback_keeps_the_blocking_stylesheet(): void
    {
        $html = wp_pwa_manager_make_stylesheet_non_blocking(self::TAG, 'wp-pwa-manager-frontend');

        $this->assertStringContainsString('<noscript>' . self::TAG . '</noscript>', $html);
    }

    public function test_existing_onload_is_not_duplicated(): void
    {
        $tag = "<link rel='stylesheet' href='x.css' media='all' onload=\"foo()\" />";

        $html = wp_pwa_manager_make_stylesheet_non_blocking($tag, 'wp-pwa-manager-frontend');

        $this->assertSame(1, preg_match_all('/onload=/', explode('<noscript>', $html)[0]));
    }
}
