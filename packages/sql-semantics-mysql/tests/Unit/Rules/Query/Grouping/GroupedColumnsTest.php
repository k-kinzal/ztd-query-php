<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Grouping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\SearchPath;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Query\Grouping\GroupedColumns;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\GroupingRule;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\NonGroupedColumn;

#[CoversClass(GroupedColumns::class)]
#[Medium]
final class GroupedColumnsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, GroupingRule|null, string}>
     */
    public static function providerCheckReportsTheFirstUndeterminedColumn(): iterable
    {
        yield 'aggregated without GROUP BY' => ['SELECT a, COUNT(*) FROM t1', GroupingRule::WithoutGroupBy, 'fz.t1.a'];
        yield 'not grouped' => ['SELECT b, COUNT(*) FROM t1 GROUP BY a', GroupingRule::NotDetermined, 'fz.t1.b'];
        yield 'primary key' => ['SELECT id, b FROM t1 GROUP BY id', null, ''];
        yield 'joined key' => ['SELECT t2.name, t1.b FROM t1 JOIN t2 ON t1.id = t2.id GROUP BY t2.id', null, ''];
        yield 'order key' => ['SELECT a FROM t1 GROUP BY a ORDER BY b', GroupingRule::NotDetermined, 'fz.t1.b'];
        yield 'constant' => ["SELECT a, b FROM t1 WHERE b = 'x' GROUP BY a", null, ''];
        yield 'expression' => ['SELECT a + 1 FROM t1 GROUP BY a + 1', null, ''];
        yield 'unique not null' => ['SELECT name, flag FROM t2 GROUP BY name', null, ''];
        yield 'alias' => ['SELECT x.b FROM t1 x GROUP BY x.a', GroupingRule::NotDetermined, 'fz.x.b'];
        yield 'aggregated order' => ['SELECT COUNT(*) FROM t1 ORDER BY a', null, ''];
        yield 'derived' => ['SELECT d.p FROM (SELECT a AS p, b FROM t1) d GROUP BY d.b', GroupingRule::NotDetermined, 'd.p'];
        yield 'distinct' => ['SELECT DISTINCT a FROM t1 ORDER BY b', GroupingRule::NotSelected, 'fz.t1.b'];
        yield 'star' => ['SELECT *, COUNT(*) FROM t1', GroupingRule::WithoutGroupBy, 'fz.t1.id'];
        yield 'rollup primary key' => ['SELECT id, b FROM t1 GROUP BY id WITH ROLLUP', GroupingRule::NotDetermined, 'fz.t1.b'];
        yield 'rollup constant' => ["SELECT a, b FROM t1 WHERE b = 'x' GROUP BY a WITH ROLLUP", GroupingRule::NotDetermined, 'fz.t1.b'];
        yield 'grouping argument' => ['SELECT a, GROUPING(b) FROM t1 GROUP BY a WITH ROLLUP', null, ''];
        yield 'qualified expression' => ['SELECT t1.a + 1 FROM t1 GROUP BY a + 1', null, ''];
        yield 'star' => ['SELECT * FROM t1 GROUP BY a', GroupingRule::NotDetermined, 'fz.t1.id'];
        yield 'star by key' => ['SELECT * FROM t1 GROUP BY id', null, ''];
        yield 'qualified star numbered by field' => ['SELECT a, t1.*, b FROM t1 GROUP BY id, a WITH ROLLUP', GroupingRule::NotDetermined, 'fz.t1.b'];
        yield 'derived key' => ['SELECT id, a FROM (SELECT * FROM t1 LIMIT 3) x GROUP BY id', null, ''];
        yield 'derived expression' => ['SELECT i2, a FROM (SELECT id, id + 1 AS i2, a FROM t1) x GROUP BY id', null, ''];
        yield 'derived grouping' => ['SELECT m FROM (SELECT a + 1 AS k, MAX(b) AS m FROM t1 GROUP BY a + 1) x GROUP BY k', null, ''];
        yield 'derived aggregate' => ['SELECT m FROM (SELECT a, id, MAX(b) AS m FROM t1 GROUP BY a, id) x GROUP BY a', GroupingRule::NotDetermined, 'x.m'];
        yield 'derived union' => ['SELECT id, a FROM (SELECT * FROM t1 UNION SELECT * FROM t1) x GROUP BY id', GroupingRule::NotDetermined, 'x.a'];
        yield 'common table' => ['WITH x AS (SELECT * FROM t1) SELECT id, a FROM x GROUP BY id', null, ''];
        yield 'outer join' => ['SELECT t2.name FROM t1 LEFT JOIN t2 ON t1.id = t2.id GROUP BY t1.id', null, ''];
        yield 'outer join reversed' => ['SELECT t1.b FROM t1 LEFT JOIN t2 ON t1.id = t2.id GROUP BY t2.id', GroupingRule::NotDetermined, 'fz.t1.b'];
        yield 'outer join expression' => ['SELECT t2.name FROM t1 LEFT JOIN t2 ON t2.id = t1.a + 1 GROUP BY t1.id', GroupingRule::NotDetermined, 'fz.t2.name'];
        yield 'using' => ['SELECT t2.name FROM t1 JOIN t2 USING (id) GROUP BY t1.id', null, ''];
        yield 'right join using' => ['SELECT t1.b FROM t1 RIGHT JOIN t2 USING (id) GROUP BY id', null, ''];
        yield 'natural join' => ['SELECT t2.name FROM t1 NATURAL JOIN t2 GROUP BY t1.id', null, ''];
        yield 'nested join' => ['SELECT t2.name FROM (t1 JOIN t2 ON t1.id = t2.id) GROUP BY t1.id', null, ''];
    }

    #[DataProvider('providerCheckReportsTheFirstUndeterminedColumn')]
    public function testCheckReportsTheFirstUndeterminedColumn(string $sql, ?GroupingRule $rule, string $column): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $tables = [
            $semantics->analyze('CREATE TABLE fz.t1 (id INT PRIMARY KEY, a INT, b VARCHAR(20))'),
            $semantics->analyze('CREATE TABLE fz.t2 (id INT PRIMARY KEY, name VARCHAR(20) NOT NULL, flag TINYINT, UNIQUE KEY uk (name))'),
        ];
        $diagnostics = $semantics->analyze($sql, $semantics->context($tables, true, new SearchPath('fz')))->facts->diagnostics;

        self::assertSame($rule === null ? 0 : 1, count($diagnostics));
        if ($rule !== null) {
            self::assertInstanceOf(NonGroupedColumn::class, $diagnostics[0]);
            self::assertSame([$rule, $column], [$diagnostics[0]->rule, $diagnostics[0]->column]);
        }
    }

    public function testItemsNumbersTheFieldsAStarSelects(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE fz.t1 (id INT PRIMARY KEY, a INT, b VARCHAR(20))');
        $diagnostics = $semantics->analyze('SELECT a, t1.*, b FROM t1 GROUP BY id, a WITH ROLLUP', $semantics->context([$table], true, new SearchPath('fz')))->facts->diagnostics;

        self::assertInstanceOf(NonGroupedColumn::class, $diagnostics[0]);
        self::assertSame(4, $diagnostics[0]->position);
    }

    public function testLeavesAnswersTheTablesOfAFromClauseThroughJoinsListsAndParentheses(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $select = $semantics->analyze('SELECT 1 FROM (t1 JOIN t2 ON 1), { OJ t3 LEFT JOIN (SELECT 1) AS d ON 1 }')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\Select::class, $select);

        self::assertCount(4, (new GroupedColumns())->leaves($select->from));
    }

    public function testJoinsAnswersTheJoinsOfAFromClause(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $select = $semantics->analyze('SELECT 1 FROM (t1 JOIN t2 ON 1), { OJ t3 LEFT JOIN (SELECT 1) AS d ON 1 }')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\Select::class, $select);

        self::assertCount(2, (new GroupedColumns())->joins($select->from));
    }

    public function testPartsAnswersWhatAListGroupsAndNullForATable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $select = $semantics->analyze('SELECT 1 FROM t1, t2')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\Select::class, $select);
        $columns = new GroupedColumns();

        self::assertCount(2, $columns->parts($select->from) ?? []);
        self::assertNull($columns->parts($columns->leaves($select->from)[0]));
    }

    public function testEqualityAValueAndNotAnExpressionDetermineAColumn(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $context = $semantics->context([$semantics->analyze('CREATE TABLE fz.t1 (id INT PRIMARY KEY, a INT, b VARCHAR(20))'), $semantics->analyze('CREATE TABLE fz.t2 (id INT PRIMARY KEY, name VARCHAR(20) NOT NULL, a INT, UNIQUE KEY uk (name))')], true, new SearchPath('fz'));

        self::assertSame(0, count($semantics->analyze("SELECT a, b FROM t1 WHERE b = 'x' GROUP BY a", $context)->facts->diagnostics));
        self::assertSame(1, count($semantics->analyze('SELECT a, b FROM t1 WHERE b = a + 1 GROUP BY a', $context)->facts->diagnostics));
    }

    public function testJoinedAnOuterJoinDeterminesOnlyItsInnerSide(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $context = $semantics->context([$semantics->analyze('CREATE TABLE fz.t1 (id INT PRIMARY KEY, a INT, b VARCHAR(20))'), $semantics->analyze('CREATE TABLE fz.t2 (id INT PRIMARY KEY, name VARCHAR(20) NOT NULL, a INT, UNIQUE KEY uk (name))')], true, new SearchPath('fz'));

        self::assertSame(0, count($semantics->analyze('SELECT t2.name FROM t1 LEFT JOIN t2 ON t1.id = t2.id GROUP BY t1.id', $context)->facts->diagnostics));
        self::assertSame(1, count($semantics->analyze('SELECT t1.b FROM t1 LEFT JOIN t2 ON t1.id = t2.id GROUP BY t2.id', $context)->facts->diagnostics));
    }

    public function testNamedFindsTheColumnsUsingAndNaturalCompare(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $context = $semantics->context([$semantics->analyze('CREATE TABLE fz.t1 (id INT PRIMARY KEY, a INT, b VARCHAR(20))'), $semantics->analyze('CREATE TABLE fz.t2 (id INT PRIMARY KEY, name VARCHAR(20) NOT NULL, a INT, UNIQUE KEY uk (name))')], true, new SearchPath('fz'));

        self::assertSame(0, count($semantics->analyze('SELECT t2.name FROM t1 JOIN t2 USING (id) GROUP BY t1.id', $context)->facts->diagnostics));
        self::assertSame(0, count($semantics->analyze('SELECT t2.name FROM t1 NATURAL JOIN t2 GROUP BY t1.id', $context)->facts->diagnostics));
    }

    public function testMembersKeepsTheInnerSideOfAnOuterJoin(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $context = $semantics->context([$semantics->analyze('CREATE TABLE fz.t1 (id INT PRIMARY KEY, a INT, b VARCHAR(20))'), $semantics->analyze('CREATE TABLE fz.t2 (id INT PRIMARY KEY, name VARCHAR(20) NOT NULL, a INT, UNIQUE KEY uk (name))')], true, new SearchPath('fz'));

        self::assertSame(0, count($semantics->analyze('SELECT t2.name FROM t1 LEFT JOIN t2 ON t2.id = 1 GROUP BY t1.a', $context)->facts->diagnostics));
        self::assertSame(1, count($semantics->analyze('SELECT t1.b FROM t1 RIGHT JOIN t2 ON t2.id = 1 GROUP BY t2.name', $context)->facts->diagnostics));
    }

    public function testDerivedCarriesTheDependenciesOfTheQueryBlock(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $context = $semantics->context([$semantics->analyze('CREATE TABLE fz.t1 (id INT PRIMARY KEY, a INT, b VARCHAR(20))'), $semantics->analyze('CREATE TABLE fz.t2 (id INT PRIMARY KEY, name VARCHAR(20) NOT NULL, a INT, UNIQUE KEY uk (name))')], true, new SearchPath('fz'));

        self::assertSame(0, count($semantics->analyze('SELECT d.c FROM (SELECT id, 5 AS c FROM t1) AS d GROUP BY d.id', $context)->facts->diagnostics));
        self::assertSame(1, count($semantics->analyze('SELECT d.p FROM (SELECT a AS p, b FROM t1) AS d GROUP BY d.b', $context)->facts->diagnostics));
    }

    public function testDependsRefusesAnAggregate(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $context = $semantics->context([$semantics->analyze('CREATE TABLE fz.t1 (id INT PRIMARY KEY, a INT, b VARCHAR(20))'), $semantics->analyze('CREATE TABLE fz.t2 (id INT PRIMARY KEY, name VARCHAR(20) NOT NULL, a INT, UNIQUE KEY uk (name))')], true, new SearchPath('fz'));

        self::assertSame(1, count($semantics->analyze('SELECT d.m FROM (SELECT MAX(a) AS m FROM t1) AS d, t1 GROUP BY t1.id', $context)->facts->diagnostics));
        self::assertSame(0, count($semantics->analyze('SELECT d.s FROM (SELECT id, (SELECT MAX(a) FROM t2) AS s FROM t1) AS d GROUP BY d.id', $context)->facts->diagnostics));
    }

    public function testClosureDeterminesTheColumnsOfAKeyBoundByWhere(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE fz.t1 (id INT PRIMARY KEY, a INT, b VARCHAR(20))');
        $operation = $semantics->analyze('SELECT a FROM t1 WHERE id = 1', $semantics->context([$table], true, new SearchPath('fz')));
        $select = $operation->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\Select::class, $select);
        self::assertNotNull($select->from);
        $relations = [spl_object_id($select->from) => new \SqlSemantics\Resolution\VisibleRelation($select->from, $operation->facts->relation($select->from)->shape)];

        self::assertCount(3, (new GroupedColumns())->closure($select, $relations, $operation->facts, []));
    }

    public function testKeyedDeterminesNothingWithoutABoundKey(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE fz.t1 (id INT PRIMARY KEY, a INT, b VARCHAR(20))');
        $operation = $semantics->analyze('SELECT a FROM t1 WHERE id = 1', $semantics->context([$table], true, new SearchPath('fz')));
        $select = $operation->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\Select::class, $select);
        self::assertNotNull($select->from);
        $relations = [spl_object_id($select->from) => new \SqlSemantics\Resolution\VisibleRelation($select->from, $operation->facts->relation($select->from)->shape)];

        self::assertCount(0, (new GroupedColumns())->keyed($relations, $operation->facts, []));
    }

    public function testBlockAnswersTheQueryBlockOfADerivedTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $operation = $semantics->analyze('SELECT 1 FROM ((SELECT 1 AS a) ORDER BY a) AS d, (SELECT 1 UNION SELECT 2) AS e');
        $select = $operation->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\Select::class, $select);
        [$derived, $union] = (new GroupedColumns())->leaves($select->from);

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\Select::class, (new GroupedColumns())->block($derived, $operation->facts));
        self::assertNull((new GroupedColumns())->block($union, $operation->facts));
    }

    public function testCheckLeavesAStatementWithAProblemAlone(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');

        self::assertCount(1, $semantics->analyze('SELECT a AS x, b AS x FROM t GROUP BY x', [$table])->facts->diagnostics);
    }

    public function testSameComparesNamesWithoutRegardToCase(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $left = $semantics->analyze('SELECT A FROM t')->statement;
        $right = $semantics->analyze('SELECT a FROM t')->statement;

        self::assertTrue((new GroupedColumns())->same($left, $right));
        self::assertFalse((new GroupedColumns())->same($left, $semantics->analyze('SELECT b FROM t')->statement));
    }
}
