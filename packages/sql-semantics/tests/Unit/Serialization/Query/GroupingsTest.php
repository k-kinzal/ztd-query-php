<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Query\Grouping\DescendingGroupKey;
use SqlSemantics\Model\Query\Grouping\EmptyGroupingSet;
use SqlSemantics\Model\Query\Grouping\GroupingSets;
use SqlSemantics\Model\Query\Grouping\Rollup;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Query\Groupings;

#[CoversClass(Groupings::class)]
#[Medium]
final class GroupingsTest extends TestCase
{
    public function testWriteSpellsAMySqlRollupWithRollup(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind('SELECT a FROM t GROUP BY ROLLUP(a)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertSame('GROUP BY `a` WITH ROLLUP', Groupings::write($statement)->toString());
    }

    public function testWriteKeepsThePostgreSqlDistinctQuantifier(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER)')))->bind('SELECT a FROM t GROUP BY DISTINCT ROLLUP(a), ()');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertSame('GROUP BY DISTINCT ROLLUP("a"), ()', Groupings::write($statement)->toString());
    }

    public function testWriteIsEmptyWithoutGrouping(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(a INT)')))->bind('SELECT a FROM t');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertSame('', Groupings::write($statement)->toString());
    }

    public function testElementWritesEachGroupingForm(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER)')))->bind('SELECT a FROM t GROUP BY a');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $key = $statement->groupBy[0];
        self::assertInstanceOf(\SqlSemantics\Model\Expression::class, $key);
        self::assertSame('GROUPING SETS(ROLLUP("a"), ())', Groupings::element(new GroupingSets([new Rollup([$key]), new EmptyGroupingSet()]))->toString());
        self::assertSame('"a" DESC', Groupings::element(new DescendingGroupKey($key))->toString());
    }
}
