<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Table\UndeclaredTable;
use SqlSemantics\Platform\PostgreSql\Semantics;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Analyze;

#[CoversClass(Analyze::class)]
#[Medium]
final class AnalyzeTest extends TestCase
{
    public function testDeriveStatementResolvesEachTable(): void
    {
        $operation = (new Semantics())->inspect('ANALYZE t, s.u');
        self::assertInstanceOf(Analyze::class, $operation->statement);
        self::assertInstanceOf(UndeclaredTable::class, $operation->facts->relation($operation->statement->targets[1])->table);
    }

    public function testDeriveStatementReportsAnOptionOfVacuum(): void
    {
        self::assertSame(['unrecognized ANALYZE option "freeze"'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), (new Semantics())->inspect('ANALYZE (FREEZE) t')->facts->diagnostics));
    }

    public function testDeriveStatementAcceptsItsOwnOptions(): void
    {
        self::assertSame([], (new Semantics())->inspect("ANALYSE (VERBOSE, SKIP_LOCKED off, BUFFER_USAGE_LIMIT '256kB') t (a)")->facts->diagnostics);
    }

    public function testRenderKeepsTheSpellingAndTheSyntax(): void
    {
        $semantics = new Semantics();
        self::assertSame(
            ['ANALYZE', 'ANALYSE VERBOSE t (a)', 'ANALYZE (verbose) t'],
            [$semantics->inspect('analyze')->toString(), $semantics->inspect('ANALYSE VERBOSE t(a)')->toString(), $semantics->inspect('ANALYZE (VERBOSE) t')->toString()],
        );
    }
}
