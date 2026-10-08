<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Grouping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\SearchPath;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Query\Grouping\ColumnReads;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

#[CoversClass(ColumnReads::class)]
#[Medium]
final class ColumnReadsTest extends TestCase
{
    public function testColumnsLooksIntoAggregatesOnlyWhenAsked(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $context = $semantics->context([$semantics->analyze('CREATE TABLE fz.t1 (id INT PRIMARY KEY, a INT, b VARCHAR(20))')], true, new SearchPath('fz'));
        $operation = $semantics->analyze('SELECT a + COUNT(b) + GROUPING(id) FROM t1 GROUP BY id WITH ROLLUP', $context);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        self::assertNotNull($select->from);
        self::assertInstanceOf(SelectExpression::class, $select->items[0]);
        $relations = [spl_object_id($select->from) => new VisibleRelation($select->from, $operation->facts->relation($select->from)->shape)];
        $columns = new ColumnReads();

        self::assertSame(['a'], array_map(static fn (array $found): ?string => $found[1]->slot->name?->value, $columns->columns($select->items[0]->expression, $relations, $operation->facts, false)));
        self::assertSame(['a', 'b', 'id'], array_map(static fn (array $found): ?string => $found[1]->slot->name?->value, $columns->columns($select->items[0]->expression, $relations, $operation->facts, true)));
        self::assertSame([], $columns->columns($select->items[0]->expression, [], $operation->facts, true));
    }

    public function testAggregateHoldsForAnAggregateWithoutAWindowAndForGrouping(): void
    {
        $select = (new Semantics(Dialect::MySql))->analyze('SELECT COUNT(a), COUNT(a) OVER (), GROUPING(a), a + 1, GROUP_CONCAT(a), JSON_OBJECTAGG(a, b) FROM t1 GROUP BY a WITH ROLLUP')->statement;
        self::assertInstanceOf(Select::class, $select);
        $reads = new ColumnReads();

        self::assertSame([true, false, true, false, true, true], array_map(static fn (object $item): bool => $item instanceof SelectExpression && $reads->aggregate($item->expression), $select->items));
    }

    public function testKeyIsTheSameForEveryUseOfOneColumnOfOneOccurrence(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $context = $semantics->context([$semantics->analyze('CREATE TABLE fz.t1 (id INT PRIMARY KEY, a INT, b VARCHAR(20))')], true, new SearchPath('fz'));
        $operation = $semantics->analyze('SELECT t1.a, A, b FROM t1', $context);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(SelectExpression::class, $select->items[0]);
        self::assertInstanceOf(SelectExpression::class, $select->items[1]);
        self::assertInstanceOf(SelectExpression::class, $select->items[2]);
        $first = $operation->facts->scalar($select->items[0]->expression)->resolution;
        $second = $operation->facts->scalar($select->items[1]->expression)->resolution;
        $third = $operation->facts->scalar($select->items[2]->expression)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $first);
        self::assertInstanceOf(ResolvedColumn::class, $second);
        self::assertInstanceOf(ResolvedColumn::class, $third);
        $reads = new ColumnReads();

        self::assertSame($reads->key($first), $reads->key($second));
        self::assertNotSame($reads->key($first), $reads->key($third));
    }

    public function testNamedAnswersTheKeysOfTheColumnsByTheirLowercaseNames(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $context = $semantics->context([$semantics->analyze('CREATE TABLE fz.t1 (Id INT PRIMARY KEY, a INT, b VARCHAR(20))')], true, new SearchPath('fz'));
        $operation = $semantics->analyze('SELECT 1 FROM t1 JOIN t1 AS x ON 1', $context);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(JoinedTable::class, $select->from);
        $relations = [
            spl_object_id($select->from->left) => new VisibleRelation($select->from->left, $operation->facts->relation($select->from->left)->shape),
            spl_object_id($select->from->right) => new VisibleRelation($select->from->right, $operation->facts->relation($select->from->right)->shape),
        ];

        $named = (new ColumnReads())->named($relations);

        self::assertSame(['id', 'a', 'b'], array_keys($named));
        self::assertSame([2, 2, 2], array_map('count', array_values($named)));
        self::assertSame([], (new ColumnReads())->named([]));
    }
}
