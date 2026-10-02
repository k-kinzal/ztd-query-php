<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Inspection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Inspection\Explain;
use SqlSemantics\Platform\Sqlite\Statement\Inspection\ExplainMode;
use SqlSemantics\Platform\Sqlite\Statement\Transaction\Begin;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Reference\Table\MissingTable;

#[CoversClass(Explain::class)]
#[Medium]
final class ExplainTest extends TestCase
{
    public function testDeriveStatementReturnsTheRowsOfTheReportAndNotThoseOfTheQuery(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('EXPLAIN SELECT 1 AS one');

        self::assertCount(8, $operation->fields() ?? []);
        self::assertSame('opcode', $operation->field(1)->name?->value);
    }

    public function testDeriveStatementDerivesTheWrappedStatement(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('EXPLAIN QUERY PLAN SELECT a FROM missing', []);

        self::assertCount(4, $operation->fields() ?? []);
        self::assertInstanceOf(MissingTable::class, $operation->facts->diagnostics[0]);
    }

    public function testDeriveStatementReportsOnAStatementThatReturnsNoRows(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('EXPLAIN BEGIN');

        self::assertCount(8, $operation->fields() ?? []);
    }

    public function testRenderWritesThePrefixOfEachMode(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);

        self::assertSame('EXPLAIN SELECT 1', $semantics->analyze('explain select 1')->toString());
        self::assertSame('EXPLAIN QUERY PLAN SELECT 1', $semantics->analyze('explain query plan select 1')->toString());
    }

    public function testRenderWritesANewlyBuiltRequest(): void
    {
        $operation = new Operation((new Semantics(Dialect::Sqlite))->context(), new Explain(ExplainMode::QueryPlan, new Begin()));

        self::assertSame('EXPLAIN QUERY PLAN BEGIN', $operation->toString());
    }
}
