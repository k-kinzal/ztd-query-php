<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Coverage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\Coverage\CoverageException;

#[CoversClass(CoverageException::class)]
final class CoverageExceptionTest extends TestCase
{
    public function testGetPreviousRetainsTheMeasurementFailureCause(): void
    {
        $cause = new RuntimeException('disk full');
        $failure = new CoverageException('checkpoint failed', 0, $cause);
        self::assertSame($cause, $failure->getPrevious());
        self::assertSame('checkpoint failed', $failure->getMessage());
    }
}
