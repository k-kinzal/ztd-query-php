<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\Exception\UnsupportedDriverException;

#[CoversClass(UnsupportedDriverException::class)]
final class UnsupportedDriverExceptionTest extends TestCase
{
    public function testDescribesTheFailure(): void
    {
        $exception = new UnsupportedDriverException('oracle');
        self::assertSame('oracle', $exception->driver);
        self::assertSame('Unsupported database driver: oracle', $exception->getMessage());
    }
}
