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
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Limit\NonConstantDefault;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
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

    public function testDeriveStatementDiscardsTheRowsAndTheDeclarationOfTheInspectedStatement(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a INTEGER)');
        $returning = $semantics->analyze('EXPLAIN INSERT INTO t (a) VALUES (1) RETURNING a, b', [$table]);
        $create = $semantics->analyze('EXPLAIN QUERY PLAN CREATE TABLE u (a, b DEFAULT (?))');
        $view = $semantics->analyze('EXPLAIN CREATE VIEW v AS SELECT a FROM t', [$table]);

        self::assertSame(['addr', 'opcode', 'p1', 'p2', 'p3', 'p4', 'p5', 'comment'], array_map(static fn (object $field): ?string => $field->name?->value, iterator_to_array($returning->fields() ?? [])));
        self::assertInstanceOf(MissingColumn::class, $returning->facts->diagnostics[0]);
        self::assertSame([], $create->declarations());
        self::assertSame(['id', 'parent', 'notused', 'detail'], array_map(static fn (object $field): ?string => $field->name?->value, iterator_to_array($create->fields() ?? [])));
        self::assertInstanceOf(NonConstantDefault::class, $create->facts->diagnostics[0]);
        self::assertSame([], $view->declarations());
        self::assertInstanceOf(Select::class, $view->statement->statement->query);
        self::assertSame($table->declarations()[0]->columns[0], $view->facts->query($view->statement->statement->query)->fields()?->at(0)->column());
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
