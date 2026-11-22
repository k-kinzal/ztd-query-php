<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Diagnostic;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\AnalysisException;
use SqlSemantics\Platform\Sqlite\Semantics;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(AnalysisException::class)]
#[Medium]
final class AnalysisExceptionTest extends TestCase
{
    public function testAnalyzeRejectsTextOutsideTheGrammar(): void
    {
        $this->expectException(AnalysisException::class);

        (new Inspection())->diagnose('SELEC 1');
    }

    public function testSplitRejectsATailOutsideTheGrammar(): void
    {
        $this->expectException(AnalysisException::class);

        (new Semantics())->split('SELECT 1; SELEC 2');
    }

    public function testSemanticProblemsAreFactsAndNotRejections(): void
    {
        $operation = (new Inspection())->diagnose('SELECT b FROM t', []);

        self::assertCount(2, $operation->facts->diagnostics);
        self::assertSame('SELECT b FROM t', $operation->toString());
    }
}
