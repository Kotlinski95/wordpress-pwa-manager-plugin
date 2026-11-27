<?php
declare(strict_types=1);

namespace Kotlinskidev\PwaManager\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;

abstract class TestCase extends PhpUnitTestCase
{
    protected array $options = array();

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        $this->options = array();

        Functions\when('get_bloginfo')->alias(static fn(string $show = ''): string => 'name' === $show ? 'Test Site' : 'Test Tagline');
        Functions\when('get_option')->alias(fn(string $name, $default = false) => $this->options[$name] ?? $default);
        Functions\when('__')->returnArg(1);
        Functions\when('home_url')->alias(static fn(string $path = ''): string => 'https://example.test' . $path);
        Functions\when('wp_json_encode')->alias(static fn($data, int $flags = 0): string|false => json_encode($data, $flags));
        Functions\when('esc_attr')->alias(static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES));
        Functions\when('esc_url')->alias(static fn($value): string => (string) $value);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }
}
