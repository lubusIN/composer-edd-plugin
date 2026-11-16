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

        $cwd = getcwd();
        $cwd = is_string($cwd) ? $cwd : '.';

        $vendorDir  = $composer->getConfig()->get('vendor-dir');
        $projectDir = is_string($vendorDir) ? dirname($vendorDir) : $cwd;

        foreach (array_unique([$projectDir, $cwd]) as $dir) {
            $envFiles = $this->discoverEnvFiles($dir);
            if (!empty($envFiles)) {
                $dotenv = Dotenv::createImmutable($dir, $envFiles, false);
                $dotenv->safeLoad();
                break;
            }
        }
    }

    /**
     * Discover all candidate .env files in a directory sorted by load priority.
     *
     * @param string $dir
     * @return array<string>
     */
    protected function discoverEnvFiles(string $dir): array
    {
        $matches = glob($dir . DIRECTORY_SEPARATOR . '.env*');
        if ($matches === false || empty($matches)) {
            return [];
        }

        $ignoredExtensions = ['example', 'sample', 'dist', 'template', 'bak', 'backup', 'old', 'php'];
        $files             = [];

        foreach ($matches as $path) {
            if (!is_file($path)) {
                continue;
            }

            $filename = basename($path);
            $ext      = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));

            if (in_array($ext, $ignoredExtensions, true)) {
                continue;
            }

            $files[] = $filename;
        }

        if (empty($files)) {
            return [];
        }

        usort($files, function (string $a, string $b): int {
            if ($a === '.env') {
                return -1;
            }
            if ($b === '.env') {
                return 1;
            }

            $aIsOverride = strpos($a, 'local') !== false || strpos($a, 'secret') !== false;
            $bIsOverride = strpos($b, 'local') !== false || strpos($b, 'secret') !== false;

            if ($aIsOverride && !$bIsOverride) {
                return 1;
            }
            if (!$aIsOverride && $bIsOverride) {
                return -1;
            }

            return strcmp($a, $b);
        });

        return $files;
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
        // Identify the source without depending on temporary download URLs or credentials.
        $event->setCustomCacheKey(hash('sha256', serialize([
            $package->getName(),
            $package->getPrettyVersion(),
            $extra['store_url'] ?? $package->getDistUrl(),
            $extra['item_id'] ?? null,
            $extra['item_name'] ?? null,
        ])));
    }

    /**
     * Resolve the EDD package download URL from the vendor API.
     *
     * @param PackageInterface $package
     * @param array<string, mixed> $extra
     * @return non-empty-string
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

        $body = $response->getBody();
        $data = json_decode(is_string($body) ? $body : '', true);
        if (!is_array($data)) {
            throw new RuntimeException(
                sprintf('Invalid JSON response received from EDD store at "%s"', $packageDistUrl)
            );
        }

        $downloadLink = $data['download_link'] ?? $data['package'] ?? null;
        if (empty($downloadLink) || !is_string($downloadLink)) {
            $errorMessage = $data['message'] ?? $data['error'] ?? $data['license'] ?? 'Unknown EDD licensing error or missing download link.';
            throw new RuntimeException(
                sprintf('EDD Error for package "%s": %s', $package->getName(), is_string($errorMessage) ? $errorMessage : 'Unknown error')
            );
        }

        return $downloadLink;
    }

    /**
     * Retrieve environment variable safely, preferring system/host environment.
     *
     * @param string $key
     * @return non-empty-string|null
     */
    protected function getEnvValue(string $key): ?string
    {
        $env = getenv($key);
        if ($env !== false && $env !== '') {
            return $env;
        }

        $val = $_SERVER[$key] ?? $_ENV[$key] ?? null;
        return ($val !== null && (string) $val !== '') ? (string) $val : null;
    }
}
