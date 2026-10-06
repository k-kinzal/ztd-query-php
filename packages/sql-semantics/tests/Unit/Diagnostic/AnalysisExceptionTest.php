<?php

declare(strict_types=1);

namespace Tests\Unit\Diagnostic;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;

#[CoversClass(AnalysisException::class)]
#[Medium]
final class AnalysisExceptionTest extends TestCase
{
    public function testAnalyzeRejectsTextOutsideTheGrammar(): void
    {
        $this->expectException(AnalysisException::class);

        (new Semantics(Dialect::Sqlite))->analyze('SELEC 1');
    }

    public function testSplitRejectsATailOutsideTheGrammar(): void
    {
        $this->expectException(AnalysisException::class);

        (new Semantics(Dialect::Sqlite))->split('SELECT 1; SELEC 2');
    }

    public function testSemanticProblemsAreFactsAndNotRejections(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT b FROM t', []);

        self::assertCount(2, $operation->facts->diagnostics);
        self::assertSame('SELECT b FROM t', $operation->toString());
    }
}
