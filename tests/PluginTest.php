<?php

declare(strict_types=1);

namespace LubusIN\ComposerEddPlugin\Tests;

use Composer\Plugin\PluginEvents;
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

        $this->assertTrue(true);
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
        $this->expectExceptionMessage("Package extra 'license' is not set.");
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
        $this->expectExceptionMessage("Package extra 'url' is not set.");
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
        $this->assertSame('vendor/pro-plugin/2.1.0', $event->getCustomCacheKey());
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
}
