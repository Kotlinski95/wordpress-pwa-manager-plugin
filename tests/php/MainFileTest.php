<?php
declare(strict_types=1);

namespace Kotlinskidev\PwaManager\Tests;

use PHPUnit\Framework\TestCase;

final class MainFileTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        $this->source = (string) file_get_contents(WP_PWA_MANAGER_PLUGIN_FILE);
    }

    public function test_header_version_matches_the_version_constant(): void
    {
        $this->assertSame(1, preg_match('/^\s*\*\s*Version:\s*(\S+)/m', $this->source, $header));
        $this->assertSame(1, preg_match("/define\('WP_PWA_MANAGER_VERSION',\s*'([^']+)'\)/", $this->source, $constant));
        $this->assertSame($header[1], $constant[1]);
    }

    public function test_header_matches_package_json_and_changelog(): void
    {
        preg_match('/^\s*\*\s*Version:\s*(\S+)/m', $this->source, $header);
        $package = json_decode((string) file_get_contents(WP_PWA_MANAGER_PLUGIN_PATH . 'package.json'), true);
        preg_match('/^## \[(\d+\.\d+\.\d+[^\]]*)\]/m', (string) file_get_contents(WP_PWA_MANAGER_PLUGIN_PATH . 'CHANGELOG.md'), $changelog);

        $this->assertSame($header[1], $package['version']);
        $this->assertSame($header[1], $changelog[1]);
    }

    public function test_header_declares_requirements_and_update_uri(): void
    {
        $this->assertMatchesRegularExpression('/Requires at least:\s*6\.4/', $this->source);
        $this->assertMatchesRegularExpression('/Requires PHP:\s*8\.1/', $this->source);
        $this->assertStringContainsString('Update URI: https://github.com/Kotlinski95/wordpress-pwa-manager-plugin', $this->source);
        $this->assertStringContainsString('Domain Path: /languages', $this->source);
    }

    public function test_frontend_script_is_deferred_and_style_is_non_blocking(): void
    {
        $this->assertStringContainsString("'strategy' => 'defer'", $this->source);
        $this->assertStringContainsString("add_filter('style_loader_tag', 'wp_pwa_manager_make_stylesheet_non_blocking'", $this->source);
        $this->assertStringContainsString("require_once WP_PWA_MANAGER_PLUGIN_PATH . 'vendor/autoload.php'", $this->source);
    }

    public function test_every_include_has_direct_access_guard(): void
    {
        $files = glob(dirname(__DIR__, 2) . '/includes/*.php');

        $this->assertNotEmpty($files);
        foreach ($files as $file) {
            $this->assertMatchesRegularExpression(
                "/if\\s*\\(\\s*!\\s*defined\\(\\s*'ABSPATH'\\s*\\)\\s*\\)\\s*\\{\\s*exit;/",
                (string) file_get_contents($file),
                basename($file)
            );
        }
    }
}
