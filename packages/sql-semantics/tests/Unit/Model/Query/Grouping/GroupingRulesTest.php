<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Grouping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Query\Grouping\Cube;
use SqlSemantics\Model\Query\Grouping\DescendingGroupKey;
use SqlSemantics\Model\Query\Grouping\EmptyGroupingSet;
use SqlSemantics\Model\Query\Grouping\GroupingRules;
use SqlSemantics\Model\Query\Grouping\GroupingSets;
use SqlSemantics\Model\Query\Grouping\Rollup;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(GroupingRules::class)]
#[Medium]
final class GroupingRulesTest extends TestCase
{
    public function testValidateAcceptsEveryPostgreSqlForm(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER)')))->bind('SELECT a FROM t GROUP BY DISTINCT a, ROLLUP(a), GROUPING SETS ((), CUBE(a))');
        self::assertInstanceOf(BoundSelect::class, $statement);
        GroupingRules::validate($statement->groupBy, $statement->origin, true);
        self::assertCount(3, $statement->groupBy);
    }

    public function testValidateRejectsDistinctOutsidePostgreSql(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind('SELECT a FROM t GROUP BY a');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $this->expectException(InvalidStructure::class);
        GroupingRules::validate($statement->groupBy, $statement->origin, true);
    }

    public function testValidateRejectsGroupingSetsInSqlite(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(a INT)')))->bind('SELECT a FROM t GROUP BY a');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(Expression::class, $statement->groupBy[0]);
        $this->expectException(InvalidStructure::class);
        GroupingRules::validate([new Rollup([$statement->groupBy[0]])], $statement->origin, false);
    }

    public function testValidateRejectsAMySqlRollupBesideOtherKeys(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind('SELECT a FROM t GROUP BY a');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(Expression::class, $statement->groupBy[0]);
        $this->expectException(InvalidStructure::class);
        $statement->withGroupBy([$statement->groupBy[0], new Rollup([$statement->groupBy[0]])]);
    }

    public function testValidateRejectsTheEmptyGroupingSetInMySql(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind('SELECT a FROM t GROUP BY a');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $this->expectException(InvalidStructure::class);
        GroupingRules::validate([new EmptyGroupingSet()], $statement->origin, false);
    }

    public function testValidateRejectsCubeBeforeMySql83(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.2.0'))->build('CREATE TABLE t(a INT)')))->bind('SELECT a FROM t GROUP BY a');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(Expression::class, $statement->groupBy[0]);
        $this->expectException(InvalidStructure::class);
        GroupingRules::validate([new Cube([$statement->groupBy[0]])], $statement->origin, false);
    }

    public function testValidateRejectsADescendingKeyInPostgreSql(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER)')))->bind('SELECT a FROM t GROUP BY a');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(Expression::class, $statement->groupBy[0]);
        $this->expectException(InvalidStructure::class);
        GroupingRules::validate([new DescendingGroupKey($statement->groupBy[0])], $statement->origin, false);
    }

    public function testMembersListsNestedElements(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER)')))->bind('SELECT a FROM t GROUP BY GROUPING SETS (ROLLUP(a), ())');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertSame([GroupingSets::class, Rollup::class, 'expression', EmptyGroupingSet::class], array_map(static fn (object $member): string => $member instanceof Expression ? 'expression' : $member::class, GroupingRules::members($statement->groupBy)));
    }
}
