<?php

declare(strict_types=1);

namespace Tests\Unit\Version;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Version\UnsupportedVersionException;

#[CoversClass(UnsupportedVersionException::class)]
final class UnsupportedVersionExceptionTest extends TestCase
{
    public function testDescribesAnUnknownVersion(): void
    {
        $exception = new UnsupportedVersionException('mysql', 'mysql-5.5.62');
        self::assertSame('mysql', $exception->dialect);
        self::assertSame('mysql-5.5.62', $exception->version);
        self::assertSame('Unsupported mysql version: mysql-5.5.62', $exception->getMessage());
    }

    public function testDescribesADialectWithoutReleases(): void
    {
        $exception = new UnsupportedVersionException('oracle', null);
        self::assertNull($exception->version);
        self::assertSame('No supported versions are registered for the oracle driver', $exception->getMessage());
    }
}
