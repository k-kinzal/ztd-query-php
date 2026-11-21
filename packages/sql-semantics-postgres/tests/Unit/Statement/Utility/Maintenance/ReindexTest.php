<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Table\UndeclaredTable;
use SqlSemantics\Platform\PostgreSql\Semantics;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Reindex;

#[CoversClass(Reindex::class)]
#[Medium]
final class ReindexTest extends TestCase
{
    public function testDeriveStatementResolvesTheTableOfReindexTable(): void
    {
        $operation = (new Semantics())->diagnose('REINDEX TABLE s.t');
        self::assertInstanceOf(Reindex::class, $operation->statement);
        self::assertInstanceOf(UndeclaredTable::class, $operation->facts->relation($operation->statement)->table);
    }

    public function testDeriveStatementReportsSystemCatalogsRebuiltConcurrently(): void
    {
        $semantics = new Semantics();
        self::assertSame(
            [['cannot reindex system catalogs concurrently'], ['cannot reindex system catalogs concurrently'], []],
            [
                array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->diagnose('REINDEX SYSTEM CONCURRENTLY')->facts->diagnostics),
                array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->diagnose('REINDEX (CONCURRENTLY) SYSTEM d')->facts->diagnostics),
                array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->diagnose('REINDEX (CONCURRENTLY false) SYSTEM')->facts->diagnostics),
            ],
        );
    }

    public function testDeriveStatementReportsATablespaceWithoutAName(): void
    {
        self::assertSame(['tablespace requires a parameter'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), (new Semantics())->diagnose('REINDEX (TABLESPACE) INDEX i')->facts->diagnostics));
    }

    public function testRenderWritesEachTarget(): void
    {
        $semantics = new Semantics();
        self::assertSame(
            ['REINDEX INDEX CONCURRENTLY s.i', 'REINDEX (verbose, tablespace x) SCHEMA "S"', 'REINDEX DATABASE', 'REINDEX SYSTEM d'],
            [$semantics->diagnose('REINDEX INDEX CONCURRENTLY s.i')->toString(), $semantics->diagnose('REINDEX (VERBOSE, TABLESPACE x) SCHEMA "S"')->toString(), $semantics->diagnose('REINDEX DATABASE')->toString(), $semantics->diagnose('REINDEX SYSTEM d')->toString()],
        );
    }

    public function testDeriveStatementReportsASequence(): void
    {
        $semantics = new Semantics();
        $context = [$semantics->diagnose('CREATE SEQUENCE s')];
        self::assertSame(['"s" is not a table or materialized view'], array_map(static fn ($problem): string => $problem->message(), $semantics->diagnose('REINDEX TABLE s', $context)->facts->diagnostics));
    }
}
