<?php
declare(strict_types=1);

namespace Kotlinskidev\PwaManager\Tests;

use Brain\Monkey\Filters;
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

final class UpdaterTest extends TestCase
{
    public function test_filter_can_disable_the_updater(): void
    {
        Filters\expectApplied('wp_pwa_manager_enable_updater')->twice()->with(true)->andReturn(false);

        $this->assertFalse(wp_pwa_manager_updater_enabled());
        $this->assertNull(wp_pwa_manager_updater_init(WP_PWA_MANAGER_PLUGIN_FILE));
    }

    public function test_updater_is_enabled_by_default(): void
    {
        $this->assertTrue(wp_pwa_manager_updater_enabled());
    }

    public function test_update_checker_is_wired_to_the_github_release_assets(): void
    {
        $source = (string) file_get_contents(WP_PWA_MANAGER_PLUGIN_PATH . 'includes/pwa-updater.php');

        $this->assertTrue(class_exists(PucFactory::class));
        $this->assertStringContainsString("'https://github.com/Kotlinski95/wordpress-pwa-manager-plugin/'", $source);
        $this->assertStringContainsString("'wordpress-pwa-manager'", $source);
        $this->assertStringContainsString("setBranch('main')", $source);
        $this->assertStringContainsString("enableReleaseAssets('/wordpress-pwa-manager\\.zip/')", $source);
    }

    public function test_disable_constant_is_honoured_in_a_separate_process(): void
    {
        $script = 'define("ABSPATH","/tmp/");define("WP_PWA_MANAGER_DISABLE_UPDATER",true);'
            . 'require "' . WP_PWA_MANAGER_PLUGIN_PATH . 'vendor/autoload.php";'
            . 'function apply_filters($tag,$value){return $value;}'
            . 'require "' . WP_PWA_MANAGER_PLUGIN_PATH . 'includes/pwa-updater.php";'
            . 'echo wp_pwa_manager_updater_enabled() ? "on" : "off";';

        $this->assertSame('off', shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script)));
    }
}
