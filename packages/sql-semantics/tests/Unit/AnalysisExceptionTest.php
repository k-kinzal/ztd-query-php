<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlSemantics\AnalysisException;

#[CoversClass(AnalysisException::class)]
#[Small]
final class AnalysisExceptionTest extends TestCase
{
    public function testCarriesTheMessageOfAConcreteException(): void
    {
        $exception = new class ('Invalid SQL') extends RuntimeException implements AnalysisException {
        };
        $message = static fn (AnalysisException $error): string => $error->getMessage();

        self::assertSame('Invalid SQL', $message($exception));
    }
}
