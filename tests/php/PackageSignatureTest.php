<?php
declare(strict_types=1);

namespace Kotlinskidev\PwaManager\Tests;

use Brain\Monkey\Functions;
use WP_Error;

final class PackageSignatureTest extends TestCase
{
    private const URL = 'https://github.com/Kotlinski95/wordpress-pwa-manager-plugin/releases/download/v1.2.0/wordpress-pwa-manager.zip';

    private string $secret;
    private string $public;
    private string $zip;

    protected function setUp(): void
    {
        parent::setUp();
        $pair = sodium_crypto_sign_keypair();
        $this->secret = sodium_crypto_sign_secretkey($pair);
        $this->public = base64_encode(sodium_crypto_sign_publickey($pair));
        $this->zip = (string) tempnam(sys_get_temp_dir(), 'pwa-zip');
        file_put_contents($this->zip, 'zip-bytes');

        Functions\when('is_wp_error')->alias(static fn($value): bool => $value instanceof WP_Error);
        Functions\when('wp_remote_retrieve_response_code')->alias(static fn($response): int => $response['code'] ?? 0);
        Functions\when('wp_remote_retrieve_body')->alias(static fn($response): string => $response['body'] ?? '');
        Functions\when('wp_delete_file')->alias(static function (string $file): void {
            @unlink($file);
        });
        Functions\when('download_url')->alias(fn(): string => $this->zip);
    }

    protected function tearDown(): void
    {
        @unlink($this->zip);
        parent::tearDown();
    }

    private function sign(string $message): string
    {
        return base64_encode(sodium_crypto_sign_detached($message, $this->secret));
    }

    private function serveSignature(array|WP_Error $response): void
    {
        Functions\when('wp_safe_remote_get')->justReturn($response);
    }

    public function test_default_constant_is_a_raw_32_byte_key(): void
    {
        $this->assertSame(SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES, strlen((string) base64_decode(WP_PWA_MANAGER_UPDATE_PUBLIC_KEY, true)));
    }

    public function test_valid_signature_verifies(): void
    {
        $this->assertTrue(wp_pwa_manager_verify_signature('zip-bytes', $this->sign('zip-bytes'), $this->public));
    }

    public function test_tampered_content_fails(): void
    {
        $this->assertFalse(wp_pwa_manager_verify_signature('zip-bytes-evil', $this->sign('zip-bytes'), $this->public));
    }

    public function test_wrong_key_fails(): void
    {
        $other = base64_encode(sodium_crypto_sign_publickey(sodium_crypto_sign_keypair()));

        $this->assertFalse(wp_pwa_manager_verify_signature('zip-bytes', $this->sign('zip-bytes'), $other));
    }

    public function test_default_public_key_rejects_a_foreign_signature(): void
    {
        $this->assertFalse(wp_pwa_manager_verify_signature('zip-bytes', $this->sign('zip-bytes')));
    }

    public function test_malformed_signature_or_key_fails(): void
    {
        $this->assertFalse(wp_pwa_manager_verify_signature('zip-bytes', 'not base64!!', $this->public));
        $this->assertFalse(wp_pwa_manager_verify_signature('zip-bytes', base64_encode('short'), $this->public));
        $this->assertFalse(wp_pwa_manager_verify_signature('zip-bytes', $this->sign('zip-bytes'), base64_encode('short')));
    }

    public function test_valid_package_url_is_accepted(): void
    {
        $this->assertTrue(wp_pwa_manager_validate_package_url(self::URL));
        $this->assertTrue(wp_pwa_manager_validate_package_url(str_replace('v1.2.0', 'v1.2.0-rc.1', self::URL)));
    }

    /**
     * @dataProvider badUrls
     */
    public function test_untrusted_package_urls_are_rejected(string $url): void
    {
        $this->assertFalse(wp_pwa_manager_validate_package_url($url));
    }

    public static function badUrls(): array
    {
        $good = self::URL;

        return array(
            'http' => array(str_replace('https', 'http', $good)),
            'other host' => array(str_replace('github.com', 'evil.test', $good)),
            'host suffix' => array(str_replace('github.com', 'github.com.evil.test', $good)),
            'userinfo' => array(str_replace('https://', 'https://github.com@evil.test/', $good)),
            'port' => array(str_replace('github.com', 'github.com:8443', $good)),
            'query' => array($good . '?x=1'),
            'fragment' => array($good . '#x'),
            'traversal' => array(str_replace('/releases/', '/../releases/', $good)),
            'other repo' => array(str_replace('wordpress-pwa-manager-plugin', 'other', $good)),
            'other asset' => array(str_replace('manager.zip', 'manager.zip.sha256', $good)),
            'bad tag' => array(str_replace('v1.2.0', 'latest', $good)),
            'whitespace' => array($good . ' '),
        );
    }

    public function test_foreign_packages_are_left_alone(): void
    {
        $this->assertFalse(wp_pwa_manager_verify_package_download(false, 'https://example.test/other.zip', null, array('plugin' => 'other/other.php')));
        $this->assertSame('/tmp/x', wp_pwa_manager_verify_package_download('/tmp/x', self::URL, null, array()));
    }

    public function test_valid_signature_returns_the_downloaded_file(): void
    {
        $this->serveSignature(array('code' => 200, 'body' => $this->sign('zip-bytes')));

        $this->assertSame($this->zip, wp_pwa_manager_verify_package_download(false, self::URL, null, array(), $this->public));
    }

    public function test_signature_from_another_key_rejects_and_deletes_the_file(): void
    {
        $this->serveSignature(array('code' => 200, 'body' => $this->sign('zip-bytes')));

        $result = wp_pwa_manager_verify_package_download(false, self::URL, null, array());

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('wp_pwa_manager_signature_invalid', $result->code);
        $this->assertFileDoesNotExist($this->zip);
    }

    public function test_missing_signature_rejects_before_downloading(): void
    {
        $this->serveSignature(array('code' => 404, 'body' => ''));
        Functions\expect('download_url')->never();

        $result = wp_pwa_manager_verify_package_download(false, self::URL, null, array());

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('wp_pwa_manager_signature_missing', $result->code);
    }

    public function test_failed_signature_request_rejects(): void
    {
        $this->serveSignature(new WP_Error('http_request_failed'));

        $result = wp_pwa_manager_verify_package_download(false, self::URL, null, array());

        $this->assertSame('wp_pwa_manager_signature_missing', $result->code);
    }

    public function test_untrusted_url_for_this_plugin_is_rejected(): void
    {
        $result = wp_pwa_manager_verify_package_download(false, 'https://evil.test/x.zip', null, array('plugin' => 'wordpress-pwa-manager/wordpress-pwa-manager.php'));

        $this->assertSame('wp_pwa_manager_package_url', $result->code);
    }

    public function test_signature_request_is_size_limited(): void
    {
        Functions\expect('wp_safe_remote_get')->once()->with(self::URL . '.sig', \Mockery::on(static fn(array $args): bool => 256 === $args['limit_response_size']))->andReturn(array('code' => 404));

        wp_pwa_manager_verify_package_download(false, self::URL, null, array());
        $this->addToAssertionCount(1);
    }

    public function test_hook_is_registered_by_the_updater_init(): void
    {
        Functions\expect('add_filter')->once()->with('upgrader_pre_download', 'wp_pwa_manager_verify_package_download', 10, 4);
        \Brain\Monkey\Filters\expectApplied('wp_pwa_manager_enable_updater')->andReturn(false);

        wp_pwa_manager_updater_init(WP_PWA_MANAGER_PLUGIN_FILE);
        $this->addToAssertionCount(1);
    }
}
