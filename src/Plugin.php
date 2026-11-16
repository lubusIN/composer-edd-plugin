<?php

declare(strict_types=1);

namespace LubusIN\ComposerEddPlugin;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\PreFileDownloadEvent;
use Dotenv\Dotenv;
use Exception;
use LubusIN\ComposerEddPlugin\Exception\MissingAuthException;
use LubusIN\ComposerEddPlugin\Exception\MissingEnvException;
use LubusIN\ComposerEddPlugin\Exception\MissingExtraException;
use RuntimeException;

/**
 * Composer EDD Plugin.
 */
class Plugin implements PluginInterface, EventSubscriberInterface
{
    protected Composer $composer;
    protected IOInterface $io;

    /**
     * Activate the plugin.
     *
     * @param Composer $composer
     * @param IOInterface $io
     */
    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io       = $io;

        $vendorDir  = $composer->getConfig()->get('vendor-dir');
        $projectDir = is_string($vendorDir) ? dirname($vendorDir) : getcwd();

        if (file_exists($projectDir . DIRECTORY_SEPARATOR . '.env')) {
            $dotenv = Dotenv::createImmutable($projectDir);
            $dotenv->safeLoad();
        } elseif (file_exists(getcwd() . DIRECTORY_SEPARATOR . '.env')) {
            $dotenv = Dotenv::createImmutable(getcwd());
            $dotenv->safeLoad();
        }
    }

    /**
     * Deactivate the plugin.
     *
     * @param Composer $composer
     * @param IOInterface $io
     */
    public function deactivate(Composer $composer, IOInterface $io): void
    {
    }

    /**
     * Prepare the plugin for uninstall.
     *
     * @param Composer $composer
     * @param IOInterface $io
     */
    public function uninstall(Composer $composer, IOInterface $io): void
    {
    }

    /**
     * Set subscribed events.
     *
     * @return array<string, array{0: string, 1?: int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            PluginEvents::PRE_FILE_DOWNLOAD => ['onPreFileDownload', 0],
        ];
    }

    /**
     * Intercept package file download and rewrite URL to EDD download link.
     *
     * @param PreFileDownloadEvent $event
     */
    public function onPreFileDownload(PreFileDownloadEvent $event): void
    {
        if ($event->getType() !== 'package') {
            return;
        }

        $package = $event->getContext();
        if (!$package instanceof PackageInterface) {
            return;
        }

        $extra = $package->getExtra();
        if (empty($extra['edd_installer'])) {
            return;
        }

        $downloadUrl = $this->resolveEddDownloadUrl($package, $extra);
        $event->setProcessedUrl($downloadUrl);
        $event->setCustomCacheKey($package->getName() . '/' . $package->getPrettyVersion());
    }

    /**
     * Resolve the EDD package download URL from the vendor API.
     *
     * @param PackageInterface $package
     * @param array<string, mixed> $extra
     * @return string
     *
     * @throws MissingExtraException
     * @throws MissingEnvException
     * @throws MissingAuthException
     * @throws RuntimeException
     */
    protected function resolveEddDownloadUrl(PackageInterface $package, array $extra): string
    {
        if (empty($extra['item_name']) && empty($extra['item_id'])) {
            throw new MissingExtraException('item_name or item_id');
        }

        $licenseVar = $extra['license_var'] ?? $extra['license'] ?? null;
        if (empty($licenseVar)) {
            throw new MissingExtraException('license (or license_var)');
        }

        $licenseKey = $this->getEnvValue((string) $licenseVar);
        if (!$licenseKey) {
            throw new MissingEnvException((string) $licenseVar);
        }

        $urlVar = $extra['url_var'] ?? $extra['url'] ?? null;
        if (empty($urlVar)) {
            throw new MissingExtraException('url (or url_var)');
        }

        $activationUrl = $this->getEnvValue((string) $urlVar);
        if (!$activationUrl && (strpos((string) $urlVar, 'http://') === 0 || strpos((string) $urlVar, 'https://') === 0)) {
            $activationUrl = (string) $urlVar;
        }
        if (!$activationUrl) {
            throw new MissingEnvException((string) $urlVar);
        }

        $packageDistUrl = $extra['store_url'] ?? $package->getDistUrl();
        if (empty($packageDistUrl)) {
            throw new RuntimeException(sprintf('Package "%s" is missing dist URL or store_url.', $package->getName()));
        }

        $params = [
            'edd_action' => 'get_version',
            'license'    => $licenseKey,
            'url'        => $activationUrl,
            'version'    => $package->getPrettyVersion(),
        ];

        if (!empty($extra['item_name'])) {
            $params['item_name'] = $extra['item_name'];
        }
        if (!empty($extra['item_id'])) {
            $params['item_id'] = $extra['item_id'];
        }

        $separator = (strpos($packageDistUrl, '?') === false) ? '?' : '&';
        $apiUrl    = $packageDistUrl . $separator . http_build_query($params);

        $this->io->writeError(
            sprintf('  - Requesting EDD package download URL for <info>%s</info>', $package->getName()),
            true,
            IOInterface::VERBOSE
        );

        try {
            $httpDownloader = $this->composer->getLoop()->getHttpDownloader();
            $response       = $httpDownloader->get($apiUrl);
        } catch (Exception $e) {
            throw new RuntimeException(
                sprintf('Unable to connect to EDD store at "%s": %s', $packageDistUrl, $e->getMessage()),
                0,
                $e
            );
        }

        $statusCode = $response->getStatusCode();
        if ($statusCode === 401 || $statusCode === 403) {
            throw new MissingAuthException(
                sprintf('Authentication failed for "%s" (HTTP %d)', $packageDistUrl, $statusCode)
            );
        }

        if ($statusCode >= 400) {
            throw new RuntimeException(
                sprintf('HTTP %d error when connecting to EDD store at "%s"', $statusCode, $packageDistUrl)
            );
        }

        $data = json_decode($response->getBody(), true);
        if (!is_array($data)) {
            throw new RuntimeException(
                sprintf('Invalid JSON response received from EDD store at "%s"', $packageDistUrl)
            );
        }

        $downloadLink = $data['download_link'] ?? $data['package'] ?? null;
        if (empty($downloadLink)) {
            $errorMessage = $data['message'] ?? $data['error'] ?? $data['license'] ?? 'Unknown EDD licensing error or missing download link.';
            throw new RuntimeException(
                sprintf('EDD Error for package "%s": %s', $package->getName(), $errorMessage)
            );
        }

        return (string) $downloadLink;
    }

    /**
     * Retrieve environment variable safely.
     *
     * @param string $key
     * @return string|null
     */
    protected function getEnvValue(string $key): ?string
    {
        $val = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        return ($val !== false && $val !== null && $val !== '') ? (string) $val : null;
    }
}
