<?php

declare(strict_types=1);

namespace LubusIN\ComposerEddPlugin\Tests;

use Composer\Composer;
use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PreFileDownloadEvent;
use Composer\Util\HttpDownloader;
use Composer\Util\Loop;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Helper to create a mock package.
     *
     * @param array<string, mixed> $extra
     * @param string|null $distUrl
     * @param string $name
     * @param string $version
     * @return PackageInterface
     */
    protected function createMockPackage(
        array $extra,
        ?string $distUrl = 'https://example.com',
        string $name = 'vendor/edd-plugin',
        string $version = '1.0.0'
    ): PackageInterface {
        $package = $this->createMock(PackageInterface::class);
        $package->method('getName')->willReturn($name);
        $package->method('getPrettyVersion')->willReturn($version);
        $package->method('getDistUrl')->willReturn($distUrl);
        $package->method('getExtra')->willReturn($extra);

        return $package;
    }

    /**
     * Helper to create a mock Composer instance.
     *
     * @param HttpDownloader|null $httpDownloader
     * @param Config|null $config
     * @return Composer
     */
    protected function createMockComposer(?HttpDownloader $httpDownloader = null, ?Config $config = null): Composer
    {
        $composer = $this->createMock(Composer::class);

        if ($config === null) {
            $config = $this->createMock(Config::class);
            $config->method('get')->with('vendor-dir')->willReturn(sys_get_temp_dir() . '/vendor');
        }
        $composer->method('getConfig')->willReturn($config);

        if ($httpDownloader !== null) {
            $loop = $this->createMock(Loop::class);
            $loop->method('getHttpDownloader')->willReturn($httpDownloader);
            $composer->method('getLoop')->willReturn($loop);
        }

        return $composer;
    }

    /**
     * Helper to create a mock IOInterface instance.
     *
     * @return IOInterface
     */
    protected function createMockIO(): IOInterface
    {
        return $this->createMock(IOInterface::class);
    }

    /**
     * Helper to create a PreFileDownloadEvent.
     *
     * @param mixed $context
     * @param non-empty-string $url
     * @param string $type
     * @param HttpDownloader|null $httpDownloader
     * @return PreFileDownloadEvent
     */
    protected function createPreFileDownloadEvent(
        $context,
        string $url = 'https://example.com/archive.zip',
        string $type = 'package',
        ?HttpDownloader $httpDownloader = null
    ): PreFileDownloadEvent {
        if ($httpDownloader === null) {
            $httpDownloader = $this->createMock(HttpDownloader::class);
        }

        return new PreFileDownloadEvent(
            PluginEvents::PRE_FILE_DOWNLOAD,
            $httpDownloader,
            $url,
            $type,
            $context
        );
    }
}
