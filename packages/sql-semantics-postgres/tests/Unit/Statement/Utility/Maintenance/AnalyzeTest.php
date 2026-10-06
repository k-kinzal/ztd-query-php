<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Analyze;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Reference\Table\UndeclaredTable;

#[CoversClass(Analyze::class)]
#[Medium]
final class AnalyzeTest extends TestCase
{
    public function testDeriveStatementResolvesEachTable(): void
    {
        $operation = (new Semantics(Dialect::PostgreSql))->analyze('ANALYZE t, s.u');
        self::assertInstanceOf(Analyze::class, $operation->statement);
        self::assertInstanceOf(UndeclaredTable::class, $operation->facts->relation($operation->statement->targets[1])->table);
    }

    public function testDeriveStatementReportsAnOptionOfVacuum(): void
    {
        self::assertSame(['unrecognized ANALYZE option "freeze"'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), (new Semantics(Dialect::PostgreSql))->analyze('ANALYZE (FREEZE) t')->facts->diagnostics));
    }

    public function testDeriveStatementAcceptsItsOwnOptions(): void
    {
        self::assertSame([], (new Semantics(Dialect::PostgreSql))->analyze("ANALYSE (VERBOSE, SKIP_LOCKED off, BUFFER_USAGE_LIMIT '256kB') t (a)")->facts->diagnostics);
    }

    public function testRenderKeepsTheSpellingAndTheSyntax(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        self::assertSame(
            ['ANALYZE', 'ANALYSE VERBOSE t (a)', 'ANALYZE (verbose) t'],
            [$semantics->analyze('analyze')->toString(), $semantics->analyze('ANALYSE VERBOSE t(a)')->toString(), $semantics->analyze('ANALYZE (VERBOSE) t')->toString()],
        );
    }
}
