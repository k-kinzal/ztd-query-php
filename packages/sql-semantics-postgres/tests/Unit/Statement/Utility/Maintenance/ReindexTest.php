<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Reindex;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Reference\Table\UndeclaredTable;

#[CoversClass(Reindex::class)]
#[Medium]
final class ReindexTest extends TestCase
{
    public function testDeriveStatementResolvesTheTableOfReindexTable(): void
    {
        $operation = (new Semantics(Dialect::PostgreSql))->analyze('REINDEX TABLE s.t');
        self::assertInstanceOf(Reindex::class, $operation->statement);
        self::assertInstanceOf(UndeclaredTable::class, $operation->facts->relation($operation->statement)->table);
    }

    public function testDeriveStatementReportsSystemCatalogsRebuiltConcurrently(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        self::assertSame(
            [['cannot reindex system catalogs concurrently'], ['cannot reindex system catalogs concurrently'], []],
            [
                array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze('REINDEX SYSTEM CONCURRENTLY')->facts->diagnostics),
                array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze('REINDEX (CONCURRENTLY) SYSTEM d')->facts->diagnostics),
                array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze('REINDEX (CONCURRENTLY false) SYSTEM')->facts->diagnostics),
            ],
        );
    }

    public function testDeriveStatementReportsATablespaceWithoutAName(): void
    {
        self::assertSame(['tablespace requires a parameter'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), (new Semantics(Dialect::PostgreSql))->analyze('REINDEX (TABLESPACE) INDEX i')->facts->diagnostics));
    }

    public function testRenderWritesEachTarget(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        self::assertSame(
            ['REINDEX INDEX CONCURRENTLY s.i', 'REINDEX (verbose, tablespace x) SCHEMA "S"', 'REINDEX DATABASE', 'REINDEX SYSTEM d'],
            [$semantics->analyze('REINDEX INDEX CONCURRENTLY s.i')->toString(), $semantics->analyze('REINDEX (VERBOSE, TABLESPACE x) SCHEMA "S"')->toString(), $semantics->analyze('REINDEX DATABASE')->toString(), $semantics->analyze('REINDEX SYSTEM d')->toString()],
        );
    }
}
