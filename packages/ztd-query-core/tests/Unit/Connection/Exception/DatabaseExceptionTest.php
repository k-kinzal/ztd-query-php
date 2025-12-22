<?php

declare(strict_types=1);

namespace Tests\Unit\Connection\Exception;

use PHPUnit\Framework\TestCase;
use ZtdQuery\Connection\Exception\DatabaseException;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DatabaseException::class)]
final class DatabaseExceptionTest extends TestCase
{
    public function testConstructWithAllParameters(): void
    {
        $previous = new \RuntimeException('root cause');
        $exception = new DatabaseException('Query failed', 1045, 42, $previous);

        self::assertSame('Query failed', $exception->getMessage());
        self::assertSame(1045, $exception->getDriverErrorCode());
        self::assertSame(42, $exception->getCode());
        self::assertSame($previous, $exception->getPrevious());
    }

    public function testConstructWithDefaults(): void
    {
        $exception = new DatabaseException('Error');

        self::assertSame('Error', $exception->getMessage());
        self::assertNull($exception->getDriverErrorCode());
        self::assertSame(0, $exception->getCode());
        self::assertNull($exception->getPrevious());
    }

    public function testGetDriverErrorCodeReturnsNull(): void
    {
        $exception = new DatabaseException('No driver code');

        self::assertNull($exception->getDriverErrorCode());
    }

    public function testGetDriverErrorCodeReturnsCode(): void
    {
        $exception = new DatabaseException('Driver error', 2002);

        self::assertSame(2002, $exception->getDriverErrorCode());
    }
}
