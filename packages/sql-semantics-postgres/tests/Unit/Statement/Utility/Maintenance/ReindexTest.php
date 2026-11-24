<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Table\UndeclaredTable;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Reindex;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(Reindex::class)]
#[Medium]
final class ReindexTest extends TestCase
{
    public function testDeriveStatementResolvesTheTableOfReindexTable(): void
    {
        $operation = (new Inspection())->diagnose('REINDEX TABLE s.t');
        self::assertInstanceOf(Reindex::class, $operation->statement);
        self::assertInstanceOf(UndeclaredTable::class, $operation->facts->relation($operation->statement)->table);
    }

    public function testDeriveStatementReportsSystemCatalogsRebuiltConcurrently(): void
    {
        $semantics = new Inspection();
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
        self::assertSame(['tablespace requires a parameter'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), (new Inspection())->diagnose('REINDEX (TABLESPACE) INDEX i')->facts->diagnostics));
    }

    public function testRenderWritesEachTarget(): void
    {
        $semantics = new Inspection();
        self::assertSame(
            ['REINDEX INDEX CONCURRENTLY s.i', 'REINDEX (verbose, tablespace x) SCHEMA "S"', 'REINDEX DATABASE', 'REINDEX SYSTEM d'],
            [$semantics->diagnose('REINDEX INDEX CONCURRENTLY s.i')->toString(), $semantics->diagnose('REINDEX (VERBOSE, TABLESPACE x) SCHEMA "S"')->toString(), $semantics->diagnose('REINDEX DATABASE')->toString(), $semantics->diagnose('REINDEX SYSTEM d')->toString()],
        );
    }

    public function testRequestsConcurrentBuildUsesOrderedOptionsAndKeywordPrecedence(): void
    {
        $semantics = new Inspection();
        $standard = $semantics->diagnose('REINDEX (CONCURRENTLY TRUE, CONCURRENTLY FALSE) INDEX app.lookup')->statement;
        $concurrent = $semantics->diagnose('REINDEX (CONCURRENTLY OFF) INDEX CONCURRENTLY app.lookup')->statement;

        self::assertInstanceOf(Reindex::class, $standard);
        self::assertInstanceOf(Reindex::class, $concurrent);
        self::assertFalse($standard->requestsConcurrentBuild());
        self::assertTrue($concurrent->requestsConcurrentBuild());
    }

    public function testDeriveStatementReportsASequence(): void
    {
        $semantics = new Inspection();
        $context = [$semantics->diagnose('CREATE SEQUENCE s')];
        self::assertSame(['"s" is not a table or materialized view'], array_map(static fn ($problem): string => $problem->message(), $semantics->diagnose('REINDEX TABLE s', $context)->facts->diagnostics));
    }
}
