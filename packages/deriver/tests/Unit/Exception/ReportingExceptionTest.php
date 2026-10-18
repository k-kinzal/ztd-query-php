<?php

declare(strict_types=1);

namespace Tests\Unit\Exception;

use Deriver\Exception\ReportingException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ReportingException::class)]
final class ReportingExceptionTest extends TestCase
{
    public function testGetMessageAndPreviousRetainTheDiagnosticAndCause(): void
    {
        $cause = new RuntimeException('cause');
        $error = new ReportingException('diagnostic', previous: $cause);
        self::assertSame('diagnostic', $error->getMessage());
        self::assertSame($cause, $error->getPrevious());
    }
}
