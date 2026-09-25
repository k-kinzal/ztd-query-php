<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Query\Grouping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Query\Grouping\GroupingBinder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Query\Grouping\Cube;
use SqlSemantics\Model\Query\Grouping\DescendingGroupKey;
use SqlSemantics\Model\Query\Grouping\EmptyGroupingSet;
use SqlSemantics\Model\Query\Grouping\GroupingSets;
use SqlSemantics\Model\Query\Grouping\Rollup;
use SqlSemantics\SchemaBuilder;

#[CoversClass(GroupingBinder::class)]
#[Medium]
final class GroupingBinderTest extends TestCase
{
    public function testBindReadsSqlitePlainKeys(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(a INT, b INT)')))->bind('SELECT a FROM t GROUP BY a, b');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertSame(['a', 'b'], array_map(static fn (object $key): ?string => $key instanceof Expression ? $key->columnBinding()?->column->name : null, $statement->groupBy));
        self::assertFalse($statement->distinctGroupingSets);
    }

    public function testBindLeavesAnUngroupedQueryWithoutKeys(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER)')))->bind('SELECT a FROM t');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertSame([], $statement->groupBy);
    }

    public function testItemsKeepThePostgreSqlWrittenOrder(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, b INTEGER)')))->bind('SELECT a FROM t GROUP BY ALL b, (), CUBE(a), GROUPING SETS (a)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertSame([Expression::class, EmptyGroupingSet::class, Cube::class, GroupingSets::class], array_map(static fn (object $item): string => $item instanceof Expression ? Expression::class : $item::class, $statement->groupBy));
        self::assertFalse($statement->distinctGroupingSets);
    }

    public function testExpressionsReadEveryRollupKey(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, b INTEGER)')))->bind('SELECT a FROM t GROUP BY ROLLUP(a, b, (a, b))');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(Rollup::class, $statement->groupBy[0]);
        self::assertCount(3, $statement->groupBy[0]->keys);
    }

    public function testKeysReadTheMySql56Directions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build('CREATE TABLE t(a INT, b INT)')))->bind('SELECT a FROM t GROUP BY a ASC, b DESC');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(Expression::class, $statement->groupBy[0]);
        self::assertInstanceOf(DescendingGroupKey::class, $statement->groupBy[1]);
    }
}
