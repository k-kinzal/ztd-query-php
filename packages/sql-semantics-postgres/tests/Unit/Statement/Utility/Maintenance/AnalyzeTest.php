<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Table\UndeclaredTable;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Analyze;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(Analyze::class)]
#[Medium]
final class AnalyzeTest extends TestCase
{
    public function testDeriveStatementResolvesEachTable(): void
    {
        $operation = (new Inspection())->diagnose('ANALYZE t, s.u');
        self::assertInstanceOf(Analyze::class, $operation->statement);
        self::assertInstanceOf(UndeclaredTable::class, $operation->facts->relation($operation->statement->targets[1])->table);
    }

    public function testDeriveStatementReportsAnOptionOfVacuum(): void
    {
        self::assertSame(['unrecognized ANALYZE option "freeze"'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), (new Inspection())->diagnose('ANALYZE (FREEZE) t')->facts->diagnostics));
    }

    public function testDeriveStatementAcceptsItsOwnOptions(): void
    {
        self::assertSame([], (new Inspection())->diagnose("ANALYSE (VERBOSE, SKIP_LOCKED off, BUFFER_USAGE_LIMIT '256kB') t (a)")->facts->diagnostics);
    }

    public function testRenderKeepsTheSpellingAndTheSyntax(): void
    {
        $semantics = new Inspection();
        self::assertSame(
            ['ANALYZE', 'ANALYSE VERBOSE t (a)', 'ANALYZE (verbose) t'],
            [$semantics->diagnose('analyze')->toString(), $semantics->diagnose('ANALYSE VERBOSE t(a)')->toString(), $semantics->diagnose('ANALYZE (VERBOSE) t')->toString()],
        );
    }
}
