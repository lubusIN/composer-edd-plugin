<?php

declare(strict_types=1);

namespace LubusIN\ComposerEddPlugin\Tests;

use Composer\Plugin\PluginEvents;
use Composer\Plugin\PreFileDownloadEvent;
use Composer\Util\Http\Response;
use Composer\Util\HttpDownloader;
use Exception;
use LubusIN\ComposerEddPlugin\Exception\MissingAuthException;
use LubusIN\ComposerEddPlugin\Exception\MissingEnvException;
use LubusIN\ComposerEddPlugin\Exception\MissingExtraException;
use LubusIN\ComposerEddPlugin\Plugin;
use RuntimeException;

class PluginTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('TEST_EDD_LICENSE');
        putenv('TEST_EDD_URL');
        unset($_ENV['TEST_EDD_LICENSE'], $_SERVER['TEST_EDD_LICENSE']);
        unset($_ENV['TEST_EDD_URL'], $_SERVER['TEST_EDD_URL']);
        parent::tearDown();
    }

    public function testSubscribedEventsRegistersPreFileDownload(): void
    {
        $events = Plugin::getSubscribedEvents();
        $this->assertArrayHasKey(PluginEvents::PRE_FILE_DOWNLOAD, $events);
        $this->assertSame(['onPreFileDownload', 0], $events[PluginEvents::PRE_FILE_DOWNLOAD]);
    }

    public function testDeactivateAndUninstallCanBeCalledWithoutErrors(): void
    {
        $plugin = new Plugin();
        $composer = $this->createMockComposer();
        $io = $this->createMockIO();

        $plugin->activate($composer, $io);
        $plugin->deactivate($composer, $io);
        $plugin->uninstall($composer, $io);

        $this->assertInstanceOf(Plugin::class, $plugin);
    }

    public function testActivateLoadsDotenvFile(): void
    {
        $tmpDir = sys_get_temp_dir() . '/edd_test_' . uniqid();
        mkdir($tmpDir);
        file_put_contents($tmpDir . '/.env', "TEST_DOTENV_KEY=custom_value\n");

        $config = $this->createMock(\Composer\Config::class);
        $config->method('get')->with('vendor-dir')->willReturn($tmpDir . '/vendor');

        $composer = $this->createMockComposer(null, $config);
        $io = $this->createMockIO();

        $plugin = new Plugin();
        $plugin->activate($composer, $io);

        $this->assertSame('custom_value', $_ENV['TEST_DOTENV_KEY'] ?? getenv('TEST_DOTENV_KEY'));

        unlink($tmpDir . '/.env');
        rmdir($tmpDir);
    }

    public function testActivateLoadsDotenvLocalFileOverridingDotenv(): void
    {
        $tmpDir = sys_get_temp_dir() . '/edd_test_' . uniqid();
        mkdir($tmpDir);
        file_put_contents($tmpDir . '/.env', "TEST_DOTENV_LOCAL_KEY=base_val\n");
        file_put_contents($tmpDir . '/.env.local', "TEST_DOTENV_LOCAL_KEY=override_val\n");

        $config = $this->createMock(\Composer\Config::class);
        $config->method('get')->with('vendor-dir')->willReturn($tmpDir . '/vendor');

        $composer = $this->createMockComposer(null, $config);
        $io = $this->createMockIO();

        $plugin = new Plugin();
        $plugin->activate($composer, $io);

        $this->assertSame('override_val', $_ENV['TEST_DOTENV_LOCAL_KEY'] ?? getenv('TEST_DOTENV_LOCAL_KEY'));

        unlink($tmpDir . '/.env');
        unlink($tmpDir . '/.env.local');
        rmdir($tmpDir);
    }

    public function testActivateLoadsDotenvSecretFileOverridingLocalAndDotenv(): void
    {
        $tmpDir = sys_get_temp_dir() . '/edd_test_' . uniqid();
        mkdir($tmpDir);
        file_put_contents($tmpDir . '/.env', "TEST_DOTENV_SECRET_KEY=base_val\n");
        file_put_contents($tmpDir . '/.env.local', "TEST_DOTENV_SECRET_KEY=local_val\n");
        file_put_contents($tmpDir . '/.env.secret', "TEST_DOTENV_SECRET_KEY=secret_val\n");

        $config = $this->createMock(\Composer\Config::class);
        $config->method('get')->with('vendor-dir')->willReturn($tmpDir . '/vendor');

        $composer = $this->createMockComposer(null, $config);
        $io = $this->createMockIO();

        $plugin = new Plugin();
        $plugin->activate($composer, $io);

        $this->assertSame('secret_val', $_ENV['TEST_DOTENV_SECRET_KEY'] ?? getenv('TEST_DOTENV_SECRET_KEY'));

        unlink($tmpDir . '/.env');
        unlink($tmpDir . '/.env.local');
        unlink($tmpDir . '/.env.secret');
        rmdir($tmpDir);
    }

    public function testActivateDynamicallyDiscoversArbitraryEnvFiles(): void
    {
        $tmpDir = sys_get_temp_dir() . '/edd_test_' . uniqid();
        mkdir($tmpDir);
        file_put_contents($tmpDir . '/.env.staging', "TEST_DOTENV_STAGING_KEY=staging_val\n");

        $config = $this->createMock(\Composer\Config::class);
        $config->method('get')->with('vendor-dir')->willReturn($tmpDir . '/vendor');

        $composer = $this->createMockComposer(null, $config);
        $io = $this->createMockIO();

        $plugin = new Plugin();
        $plugin->activate($composer, $io);

        $this->assertSame('staging_val', $_ENV['TEST_DOTENV_STAGING_KEY'] ?? getenv('TEST_DOTENV_STAGING_KEY'));

        unlink($tmpDir . '/.env.staging');
        rmdir($tmpDir);
    }

    public function testActivateIgnoresTemplateAndBackupEnvFiles(): void
    {
        $tmpDir = sys_get_temp_dir() . '/edd_test_' . uniqid();
        mkdir($tmpDir);
        file_put_contents($tmpDir . '/.env.example', "TEST_DOTENV_EXAMPLE_KEY=example_val\n");
        file_put_contents($tmpDir . '/.env.dist', "TEST_DOTENV_DIST_KEY=dist_val\n");
        file_put_contents($tmpDir . '/.env.bak', "TEST_DOTENV_BAK_KEY=bak_val\n");

        $config = $this->createMock(\Composer\Config::class);
        $config->method('get')->with('vendor-dir')->willReturn($tmpDir . '/vendor');

        $composer = $this->createMockComposer(null, $config);
        $io = $this->createMockIO();

        $plugin = new Plugin();
        $plugin->activate($composer, $io);

        $this->assertArrayNotHasKey('TEST_DOTENV_EXAMPLE_KEY', $_ENV);
        $this->assertArrayNotHasKey('TEST_DOTENV_DIST_KEY', $_ENV);
        $this->assertArrayNotHasKey('TEST_DOTENV_BAK_KEY', $_ENV);

        unlink($tmpDir . '/.env.example');
        unlink($tmpDir . '/.env.dist');
        unlink($tmpDir . '/.env.bak');
        rmdir($tmpDir);
    }

    public function testIgnoresNonPackageTypeDownloads(): void
    {
        $plugin = new Plugin();
        $plugin->activate($this->createMockComposer(), $this->createMockIO());

        $event = $this->createPreFileDownloadEvent(
            null,
            'https://example.com/metadata.json',
            'metadata'
        );

        $plugin->onPreFileDownload($event);
        $this->assertSame('https://example.com/metadata.json', $event->getProcessedUrl());
    }

    public function testIgnoresPackageWithoutEddInstallerExtra(): void
    {
        $plugin = new Plugin();
        $plugin->activate($this->createMockComposer(), $this->createMockIO());

        $package = $this->createMockPackage([]);
        $event = $this->createPreFileDownloadEvent($package, 'https://example.com/original.zip');

        $plugin->onPreFileDownload($event);
        $this->assertSame('https://example.com/original.zip', $event->getProcessedUrl());
    }

    public function testThrowsWhenItemNameAndItemIdAreMissing(): void
    {
        $plugin = new Plugin();
        $plugin->activate($this->createMockComposer(), $this->createMockIO());

        $package = $this->createMockPackage([
            'edd_installer' => true,
        ]);
        $event = $this->createPreFileDownloadEvent($package);

        $this->expectException(MissingExtraException::class);
        $this->expectExceptionMessage("Package extra 'item_name or item_id' is not set.");
        $plugin->onPreFileDownload($event);
    }

    public function testThrowsWhenLicenseIsMissingInExtra(): void
    {
        $plugin = new Plugin();
        $plugin->activate($this->createMockComposer(), $this->createMockIO());

        $package = $this->createMockPackage([
            'edd_installer' => true,
            'item_name'     => 'My Plugin',
        ]);
        $event = $this->createPreFileDownloadEvent($package);

        $this->expectException(MissingExtraException::class);
        $this->expectExceptionMessage("Package extra 'license (or license_var)' is not set.");
        $plugin->onPreFileDownload($event);
    }

    public function testThrowsWhenLicenseEnvVariableNotSet(): void
    {
        $plugin = new Plugin();
        $plugin->activate($this->createMockComposer(), $this->createMockIO());

        $package = $this->createMockPackage([
            'edd_installer' => true,
            'item_name'     => 'My Plugin',
            'license'       => 'MISSING_ENV_LICENSE',
        ]);
        $event = $this->createPreFileDownloadEvent($package);

        $this->expectException(MissingEnvException::class);
        $this->expectExceptionMessage("Environment variable 'MISSING_ENV_LICENSE' is not set.");
        $plugin->onPreFileDownload($event);
    }

    public function testThrowsWhenUrlIsMissingInExtra(): void
    {
        putenv('TEST_EDD_LICENSE=valid_license');

        $plugin = new Plugin();
        $plugin->activate($this->createMockComposer(), $this->createMockIO());

        $package = $this->createMockPackage([
            'edd_installer' => true,
            'item_name'     => 'My Plugin',
            'license'       => 'TEST_EDD_LICENSE',
        ]);
        $event = $this->createPreFileDownloadEvent($package);

        $this->expectException(MissingExtraException::class);
        $this->expectExceptionMessage("Package extra 'url (or url_var)' is not set.");
        $plugin->onPreFileDownload($event);
    }

    public function testThrowsWhenUrlEnvVariableNotSet(): void
    {
        putenv('TEST_EDD_LICENSE=valid_license');

        $plugin = new Plugin();
        $plugin->activate($this->createMockComposer(), $this->createMockIO());

        $package = $this->createMockPackage([
            'edd_installer' => true,
            'item_name'     => 'My Plugin',
            'license'       => 'TEST_EDD_LICENSE',
            'url'           => 'MISSING_URL_ENV',
        ]);
        $event = $this->createPreFileDownloadEvent($package);

        $this->expectException(MissingEnvException::class);
        $this->expectExceptionMessage("Environment variable 'MISSING_URL_ENV' is not set.");
        $plugin->onPreFileDownload($event);
    }

    public function testAcceptsDirectUrlInExtra(): void
    {
        putenv('TEST_EDD_LICENSE=valid_license');

        $response = $this->createMock(Response::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn(json_encode([
            'download_link' => 'https://store.test/downloads/edd-plugin.zip',
        ]));

        $httpDownloader = $this->createMock(HttpDownloader::class);
        $httpDownloader->expects($this->once())
            ->method('get')
            ->with($this->callback(function (string $url) {
                return strpos($url, 'url=https%3A%2F%2Fmysite.test') !== false;
            }))
            ->willReturn($response);

        $plugin = new Plugin();
        $plugin->activate($this->createMockComposer($httpDownloader), $this->createMockIO());

        $package = $this->createMockPackage([
            'edd_installer' => true,
            'item_name'     => 'My Plugin',
            'license'       => 'TEST_EDD_LICENSE',
            'url'           => 'https://mysite.test',
        ], 'https://store.test');
        $event = $this->createPreFileDownloadEvent($package, 'https://store.test/original.zip', 'package', $httpDownloader);

        $plugin->onPreFileDownload($event);

        $this->assertSame('https://store.test/downloads/edd-plugin.zip', $event->getProcessedUrl());
    }

    public function testThrowsWhenPackageDistUrlMissing(): void
    {
        putenv('TEST_EDD_LICENSE=valid_license');
        putenv('TEST_EDD_URL=https://mysite.test');

        $plugin = new Plugin();
        $plugin->activate($this->createMockComposer(), $this->createMockIO());

        $package = $this->createMockPackage([
            'edd_installer' => true,
            'item_name'     => 'My Plugin',
            'license'       => 'TEST_EDD_LICENSE',
            'url'           => 'TEST_EDD_URL',
        ], null);
        $event = $this->createPreFileDownloadEvent($package);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('is missing dist URL or store_url');
        $plugin->onPreFileDownload($event);
    }

    public function testSuccessfulDownloadUrlRewritingWithItemName(): void
    {
        putenv('TEST_EDD_LICENSE=secret_license_key');
        putenv('TEST_EDD_URL=https://client.site');

        $response = $this->createMock(Response::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn(json_encode([
            'download_link' => 'https://store.test/edd-sl/package_download/token123.zip',
        ]));

        $httpDownloader = $this->createMock(HttpDownloader::class);
        $httpDownloader->expects($this->once())
            ->method('get')
            ->with($this->callback(function (string $url) {
                return strpos($url, 'edd_action=get_version') !== false
                    && strpos($url, 'item_name=Pro+Plugin') !== false
                    && strpos($url, 'license=secret_license_key') !== false
                    && strpos($url, 'version=2.1.0') !== false;
            }))
            ->willReturn($response);

        $plugin = new Plugin();
        $plugin->activate($this->createMockComposer($httpDownloader), $this->createMockIO());

        $package = $this->createMockPackage([
            'edd_installer' => true,
            'item_name'     => 'Pro Plugin',
            'license'       => 'TEST_EDD_LICENSE',
            'url'           => 'TEST_EDD_URL',
        ], 'https://store.test/edd-api', 'vendor/pro-plugin', '2.1.0');

        $event = $this->createPreFileDownloadEvent($package, 'https://store.test/edd-api', 'package', $httpDownloader);

        $plugin->onPreFileDownload($event);

        $this->assertSame(
            'https://store.test/edd-sl/package_download/token123.zip',
            $event->getProcessedUrl()
        );
        $this->assertNotEmpty($event->getCustomCacheKey());
    }

    public function testCacheKeysSeparateSourcesAndRemainStableAcrossDownloadTokens(): void
    {
        putenv('TEST_EDD_LICENSE=secret_license_key');

        $httpDownloader = $this->createMock(HttpDownloader::class);
        $token = 0;
        $httpDownloader->method('get')->willReturnCallback(function () use (&$token): Response {
            return new Response(['url' => 'https://store.test'], 200, [], (string) json_encode([
                'download_link' => 'https://downloads.test/archive.zip?token=' . ++$token,
            ]));
        });

        $plugin = new Plugin();
        $plugin->activate($this->createMockComposer($httpDownloader), $this->createMockIO());

        $extra = [
            'edd_installer' => true,
            'item_id'       => 42,
            'item_name'     => 'Pro Plugin',
            'license_var'   => 'TEST_EDD_LICENSE',
            'url_var'       => 'https://client.test',
        ];

        /**
         * @param array<string, mixed> $options
         */
        $resolve = function (array $options, string $distUrl = 'https://store.test') use ($plugin, $httpDownloader): PreFileDownloadEvent {
            $package = $this->createMockPackage($options, $distUrl);
            $event = $this->createPreFileDownloadEvent($package, 'https://example.com/archive.zip', 'package', $httpDownloader);
            $plugin->onPreFileDownload($event);

            return $event;
        };

        $first = $resolve($extra);
        $second = $resolve($extra);
        $key = $first->getCustomCacheKey();
        $this->assertNotEmpty($key);
        $this->assertNotSame($first->getProcessedUrl(), $second->getProcessedUrl());
        $this->assertSame($key, $second->getCustomCacheKey());
        $this->assertNotSame($key, $resolve($extra, 'https://other-store.test')->getCustomCacheKey());
        $this->assertNotSame($key, $resolve(array_replace($extra, ['item_id' => 43]))->getCustomCacheKey());
        $this->assertNotSame($key, $resolve(array_replace($extra, ['item_name' => 'Other Plugin']))->getCustomCacheKey());

        // An explicit store_url is the effective source, regardless of dist.url.
        $override = array_replace($extra, ['store_url' => 'https://store.test']);
        $this->assertSame($key, $resolve($override, 'https://unused.test')->getCustomCacheKey());
        $this->assertNotSame($key, $resolve(array_replace($extra, ['store_url' => 'https://other-store.test']))->getCustomCacheKey());

        // Name-only configurations must also distinguish products.
        unset($extra['item_id']);
        $this->assertNotSame(
            $resolve($extra)->getCustomCacheKey(),
            $resolve(array_replace($extra, ['item_name' => 'Other Plugin']))->getCustomCacheKey()
        );

        putenv('TEST_EDD_LICENSE=another_license');
        $this->assertSame($key, $resolve($override)->getCustomCacheKey());
    }

    public function testSuccessfulDownloadUrlRewritingWithItemId(): void
    {
        putenv('TEST_EDD_LICENSE=secret_license_key');
        putenv('TEST_EDD_URL=https://client.site');

        $response = $this->createMock(Response::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn(json_encode([
            'download_link' => 'https://store.test/download_42.zip',
        ]));

        $httpDownloader = $this->createMock(HttpDownloader::class);
        $httpDownloader->expects($this->once())
            ->method('get')
            ->with($this->callback(function (string $url) {
                return strpos($url, 'item_id=42') !== false;
            }))
            ->willReturn($response);

        $plugin = new Plugin();
        $plugin->activate($this->createMockComposer($httpDownloader), $this->createMockIO());

        $package = $this->createMockPackage([
            'edd_installer' => true,
            'item_id'       => 42,
            'license'       => 'TEST_EDD_LICENSE',
            'url'           => 'TEST_EDD_URL',
        ], 'https://store.test');

        $event = $this->createPreFileDownloadEvent($package, 'https://store.test', 'package', $httpDownloader);

        $plugin->onPreFileDownload($event);
        $this->assertSame('https://store.test/download_42.zip', $event->getProcessedUrl());
    }

    public function testSuccessfulDownloadUrlRewritingWithLicenseVarAndUrlVar(): void
    {
        putenv('TEST_EDD_LICENSE=secret_license_key');
        putenv('TEST_EDD_URL=https://client.site');

        $response = $this->createMock(Response::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn(json_encode([
            'download_link' => 'https://store.test/download_var.zip',
        ]));

        $httpDownloader = $this->createMock(HttpDownloader::class);
        $httpDownloader->expects($this->once())
            ->method('get')
            ->with($this->callback(function (string $url) {
                return strpos($url, 'license=secret_license_key') !== false
                    && strpos($url, 'url=https%3A%2F%2Fclient.site') !== false;
            }))
            ->willReturn($response);

        $plugin = new Plugin();
        $plugin->activate($this->createMockComposer($httpDownloader), $this->createMockIO());

        $package = $this->createMockPackage([
            'edd_installer' => true,
            'item_id'       => 99,
            'license_var'   => 'TEST_EDD_LICENSE',
            'url_var'       => 'TEST_EDD_URL',
        ], 'https://store.test');

        $event = $this->createPreFileDownloadEvent($package, 'https://store.test', 'package', $httpDownloader);

        $plugin->onPreFileDownload($event);
        $this->assertSame('https://store.test/download_var.zip', $event->getProcessedUrl());
    }

    public function testFallbackToPackageFieldInResponse(): void
    {
        putenv('TEST_EDD_LICENSE=secret_license_key');
        putenv('TEST_EDD_URL=https://client.site');

        $response = $this->createMock(Response::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn(json_encode([
            'package' => 'https://store.test/package_field_download.zip',
        ]));

        $httpDownloader = $this->createMock(HttpDownloader::class);
        $httpDownloader->method('get')->willReturn($response);

        $plugin = new Plugin();
        $plugin->activate($this->createMockComposer($httpDownloader), $this->createMockIO());

        $package = $this->createMockPackage([
            'edd_installer' => true,
            'item_name'     => 'Pro Plugin',
            'license'       => 'TEST_EDD_LICENSE',
            'url'           => 'TEST_EDD_URL',
        ], 'https://store.test');

        $event = $this->createPreFileDownloadEvent($package, 'https://store.test', 'package', $httpDownloader);
        $plugin->onPreFileDownload($event);

        $this->assertSame('https://store.test/package_field_download.zip', $event->getProcessedUrl());
    }

    public function testThrowsWhenEddStoreReturnsError(): void
    {
        putenv('TEST_EDD_LICENSE=expired_key');
        putenv('TEST_EDD_URL=https://client.site');

        $response = $this->createMock(Response::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn(json_encode([
            'success' => false,
            'error'   => 'expired',
            'message' => 'Your license key for this product has expired.',
        ]));

        $httpDownloader = $this->createMock(HttpDownloader::class);
        $httpDownloader->method('get')->willReturn($response);

        $plugin = new Plugin();
        $plugin->activate($this->createMockComposer($httpDownloader), $this->createMockIO());

        $package = $this->createMockPackage([
            'edd_installer' => true,
            'item_name'     => 'Pro Plugin',
            'license'       => 'TEST_EDD_LICENSE',
            'url'           => 'TEST_EDD_URL',
        ], 'https://store.test');

        $event = $this->createPreFileDownloadEvent($package, 'https://store.test', 'package', $httpDownloader);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Your license key for this product has expired.');
        $plugin->onPreFileDownload($event);
    }

    public function testThrowsMissingAuthExceptionOn401(): void
    {
        putenv('TEST_EDD_LICENSE=valid_key');
        putenv('TEST_EDD_URL=https://client.site');

        $response = $this->createMock(Response::class);
        $response->method('getStatusCode')->willReturn(401);

        $httpDownloader = $this->createMock(HttpDownloader::class);
        $httpDownloader->method('get')->willReturn($response);

        $plugin = new Plugin();
        $plugin->activate($this->createMockComposer($httpDownloader), $this->createMockIO());

        $package = $this->createMockPackage([
            'edd_installer' => true,
            'item_name'     => 'Pro Plugin',
            'license'       => 'TEST_EDD_LICENSE',
            'url'           => 'TEST_EDD_URL',
        ], 'https://store.test');

        $event = $this->createPreFileDownloadEvent($package, 'https://store.test', 'package', $httpDownloader);

        $this->expectException(MissingAuthException::class);
        $this->expectExceptionMessage('Authentication failed for "https://store.test" (HTTP 401)');
        $plugin->onPreFileDownload($event);
    }

    public function testThrowsMissingAuthExceptionOn403(): void
    {
        putenv('TEST_EDD_LICENSE=valid_key');
        putenv('TEST_EDD_URL=https://client.site');

        $response = $this->createMock(Response::class);
        $response->method('getStatusCode')->willReturn(403);

        $httpDownloader = $this->createMock(HttpDownloader::class);
        $httpDownloader->method('get')->willReturn($response);

        $plugin = new Plugin();
        $plugin->activate($this->createMockComposer($httpDownloader), $this->createMockIO());

        $package = $this->createMockPackage([
            'edd_installer' => true,
            'item_name'     => 'Pro Plugin',
            'license'       => 'TEST_EDD_LICENSE',
            'url'           => 'TEST_EDD_URL',
        ], 'https://store.test');

        $event = $this->createPreFileDownloadEvent($package, 'https://store.test', 'package', $httpDownloader);

        $this->expectException(MissingAuthException::class);
        $this->expectExceptionMessage('Authentication failed for "https://store.test" (HTTP 403)');
        $plugin->onPreFileDownload($event);
    }

    public function testThrowsRuntimeExceptionOn500(): void
    {
        putenv('TEST_EDD_LICENSE=valid_key');
        putenv('TEST_EDD_URL=https://client.site');

        $response = $this->createMock(Response::class);
        $response->method('getStatusCode')->willReturn(500);

        $httpDownloader = $this->createMock(HttpDownloader::class);
        $httpDownloader->method('get')->willReturn($response);

        $plugin = new Plugin();
        $plugin->activate($this->createMockComposer($httpDownloader), $this->createMockIO());

        $package = $this->createMockPackage([
            'edd_installer' => true,
            'item_name'     => 'Pro Plugin',
            'license'       => 'TEST_EDD_LICENSE',
            'url'           => 'TEST_EDD_URL',
        ], 'https://store.test');

        $event = $this->createPreFileDownloadEvent($package, 'https://store.test', 'package', $httpDownloader);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 500 error when connecting to EDD store');
        $plugin->onPreFileDownload($event);
    }

    public function testThrowsRuntimeExceptionOnInvalidJsonResponse(): void
    {
        putenv('TEST_EDD_LICENSE=valid_key');
        putenv('TEST_EDD_URL=https://client.site');

        $response = $this->createMock(Response::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn('<html>Not JSON</html>');

        $httpDownloader = $this->createMock(HttpDownloader::class);
        $httpDownloader->method('get')->willReturn($response);

        $plugin = new Plugin();
        $plugin->activate($this->createMockComposer($httpDownloader), $this->createMockIO());

        $package = $this->createMockPackage([
            'edd_installer' => true,
            'item_name'     => 'Pro Plugin',
            'license'       => 'TEST_EDD_LICENSE',
            'url'           => 'TEST_EDD_URL',
        ], 'https://store.test');

        $event = $this->createPreFileDownloadEvent($package, 'https://store.test', 'package', $httpDownloader);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON response received from EDD store');
        $plugin->onPreFileDownload($event);
    }

    public function testThrowsRuntimeExceptionOnHttpDownloaderFailure(): void
    {
        putenv('TEST_EDD_LICENSE=valid_key');
        putenv('TEST_EDD_URL=https://client.site');

        $httpDownloader = $this->createMock(HttpDownloader::class);
        $httpDownloader->method('get')->willThrowException(new Exception('Connection timed out'));

        $plugin = new Plugin();
        $plugin->activate($this->createMockComposer($httpDownloader), $this->createMockIO());

        $package = $this->createMockPackage([
            'edd_installer' => true,
            'item_name'     => 'Pro Plugin',
            'license'       => 'TEST_EDD_LICENSE',
            'url'           => 'TEST_EDD_URL',
        ], 'https://store.test');

        $event = $this->createPreFileDownloadEvent($package, 'https://store.test', 'package', $httpDownloader);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to connect to EDD store at "https://store.test": Connection timed out');
        $plugin->onPreFileDownload($event);
    }

    public function testComposerJsonDeclaresPluginModifiesDownloads(): void
    {
        $composerJsonPath = dirname(__DIR__) . '/composer.json';
        $contents = (string) file_get_contents($composerJsonPath);
        $data = json_decode($contents, true);

        $this->assertIsArray($data);
        $this->assertSame(Plugin::class, $data['extra']['class'] ?? null);
        $this->assertTrue($data['extra']['plugin-modifies-downloads'] ?? false);
    }
}
