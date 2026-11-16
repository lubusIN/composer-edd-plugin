<?php

declare(strict_types=1);

namespace LubusIN\ComposerEddPlugin\Tests;

use Exception;
use LubusIN\ComposerEddPlugin\Exception\MissingAuthException;
use LubusIN\ComposerEddPlugin\Exception\MissingEnvException;
use LubusIN\ComposerEddPlugin\Exception\MissingExtraException;
use PHPUnit\Framework\TestCase;

class ExceptionTest extends TestCase
{
    public function testMissingExtraException(): void
    {
        $exception = new MissingExtraException('license');
        $this->assertInstanceOf(Exception::class, $exception);
        $this->assertSame("Package extra 'license' is not set.", $exception->getMessage());
    }

    public function testMissingEnvException(): void
    {
        $exception = new MissingEnvException('PRODUCT_LICENSE');
        $this->assertInstanceOf(Exception::class, $exception);
        $this->assertSame("Environment variable 'PRODUCT_LICENSE' is not set.", $exception->getMessage());
    }

    public function testMissingAuthException(): void
    {
        $exception = new MissingAuthException('Authentication failed for store');
        $this->assertInstanceOf(Exception::class, $exception);
        $this->assertSame(
            'Authentication failed for store (see https://github.com/lubusIN/composer-edd-plugin for details)',
            $exception->getMessage()
        );
    }
}
