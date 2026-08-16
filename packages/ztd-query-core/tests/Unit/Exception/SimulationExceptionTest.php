<?php

declare(strict_types=1);

namespace Tests\Unit\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZtdQuery\Exception\SimulationException;

#[CoversClass(SimulationException::class)]
final class SimulationExceptionTest extends TestCase
{
    public function testProvidesTypedRuntimeExceptionBoundary(): void
    {
        $exception = new class ('simulation failed') extends SimulationException {
        };

        self::assertInstanceOf(RuntimeException::class, $exception);
        self::assertSame('simulation failed', $exception->getMessage());
    }
}
