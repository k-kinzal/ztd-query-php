<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Diagnostic;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\SemanticException;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\Diagnostic;

#[CoversClass(SemanticException::class)]
#[Small]
final class SemanticExceptionTest extends TestCase
{
    public function testKeepsDiagnosticsInTheirReportOrder(): void
    {
        $first = self::createStub(Diagnostic::class);
        $first->method('message')->willReturn('First problem.');
        $second = self::createStub(Diagnostic::class);
        $second->method('message')->willReturn('Second problem.');
        $error = new SemanticException([$first, $second]);

        self::assertSame([$first, $second], $error->diagnostics);
        self::assertSame("First problem.\nSecond problem.", $error->getMessage());
    }
}
