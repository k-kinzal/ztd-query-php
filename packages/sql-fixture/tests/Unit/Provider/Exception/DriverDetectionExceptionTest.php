<?php

declare(strict_types=1);

namespace Tests\Unit\Provider\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Provider\Exception\DriverDetectionException;

#[CoversClass(DriverDetectionException::class)]
final class DriverDetectionExceptionTest extends TestCase
{
    public function testDescribesTheFailure(): void
    {
        $exception = new DriverDetectionException();
        self::assertSame('Unable to detect PDO driver', $exception->getMessage());
    }
}
