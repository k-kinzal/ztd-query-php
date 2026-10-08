<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Grouping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\SearchPath;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Query\Grouping\GroupedColumns;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\Logical;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\GroupingRule;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\NonGroupedColumn;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Resolution\VisibleRelation;

#[CoversClass(GroupedColumns::class)]
#[Medium]
final class GroupedColumnsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, GroupingRule, string}>
     */
    public static function providerCheckReportsTheFirstUndeterminedColumn(): iterable
    {
        yield 'aggregated without GROUP BY' => ['SELECT a, COUNT(*) FROM t1', GroupingRule::WithoutGroupBy, 'fz.t1.a'];
        yield 'not grouped' => ['SELECT b, COUNT(*) FROM t1 GROUP BY a', GroupingRule::NotDetermined, 'fz.t1.b'];
        yield 'order key' => ['SELECT a FROM t1 GROUP BY a ORDER BY b', GroupingRule::NotDetermined, 'fz.t1.b'];
        yield 'alias' => ['SELECT x.b FROM t1 x GROUP BY x.a', GroupingRule::NotDetermined, 'fz.x.b'];
        yield 'derived' => ['SELECT d.p FROM (SELECT a AS p, b FROM t1) d GROUP BY d.b', GroupingRule::NotDetermined, 'd.p'];
        yield 'distinct' => ['SELECT DISTINCT a FROM t1 ORDER BY b', GroupingRule::NotSelected, 'fz.t1.b'];
        yield 'star' => ['SELECT *, COUNT(*) FROM t1', GroupingRule::WithoutGroupBy, 'fz.t1.id'];
        yield 'rollup primary key' => ['SELECT id, b FROM t1 GROUP BY id WITH ROLLUP', GroupingRule::NotDetermined, 'fz.t1.b'];
        yield 'rollup constant' => ["SELECT a, b FROM t1 WHERE b = 'x' GROUP BY a WITH ROLLUP", GroupingRule::NotDetermined, 'fz.t1.b'];
        yield 'star grouped by a column' => ['SELECT * FROM t1 GROUP BY a', GroupingRule::NotDetermined, 'fz.t1.id'];
        yield 'qualified star numbered by field' => ['SELECT a, t1.*, b FROM t1 GROUP BY id, a WITH ROLLUP', GroupingRule::NotDetermined, 'fz.t1.b'];
        yield 'derived aggregate' => ['SELECT m FROM (SELECT a, id, MAX(b) AS m FROM t1 GROUP BY a, id) x GROUP BY a', GroupingRule::NotDetermined, 'x.m'];
        yield 'derived union' => ['SELECT id, a FROM (SELECT * FROM t1 UNION SELECT * FROM t1) x GROUP BY id', GroupingRule::NotDetermined, 'x.a'];
        yield 'outer join reversed' => ['SELECT t1.b FROM t1 LEFT JOIN t2 ON t1.id = t2.id GROUP BY t2.id', GroupingRule::NotDetermined, 'fz.t1.b'];
        yield 'outer join expression' => ['SELECT t2.name FROM t1 LEFT JOIN t2 ON t2.id = t1.a + 1 GROUP BY t1.id', GroupingRule::NotDetermined, 'fz.t2.name'];
    }

    #[DataProvider('providerCheckReportsTheFirstUndeterminedColumn')]
    public function testCheckReportsTheFirstUndeterminedColumn(string $sql, GroupingRule $rule, string $column): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $tables = [
            $semantics->analyze('CREATE TABLE fz.t1 (id INT PRIMARY KEY, a INT, b VARCHAR(20))'),
            $semantics->analyze('CREATE TABLE fz.t2 (id INT PRIMARY KEY, name VARCHAR(20) NOT NULL, flag TINYINT, UNIQUE KEY uk (name))'),
        ];
        $diagnostics = $semantics->analyze($sql, $semantics->context($tables, true, new SearchPath('fz')))->facts->diagnostics;

        self::assertCount(1, $diagnostics);
        self::assertInstanceOf(NonGroupedColumn::class, $diagnostics[0]);
        self::assertSame([$rule, $column], [$diagnostics[0]->rule, $diagnostics[0]->column]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerCheckAcceptsColumnsTheRowsDetermine(): iterable
    {
        yield 'primary key' => ['SELECT id, b FROM t1 GROUP BY id'];
        yield 'joined key' => ['SELECT t2.name, t1.b FROM t1 JOIN t2 ON t1.id = t2.id GROUP BY t2.id'];
        yield 'constant' => ["SELECT a, b FROM t1 WHERE b = 'x' GROUP BY a"];
        yield 'expression' => ['SELECT a + 1 FROM t1 GROUP BY a + 1'];
        yield 'unique not null' => ['SELECT name, flag FROM t2 GROUP BY name'];
        yield 'aggregated order' => ['SELECT COUNT(*) FROM t1 ORDER BY a'];
        yield 'grouping argument' => ['SELECT a, GROUPING(b) FROM t1 GROUP BY a WITH ROLLUP'];
        yield 'qualified expression' => ['SELECT t1.a + 1 FROM t1 GROUP BY a + 1'];
        yield 'star by key' => ['SELECT * FROM t1 GROUP BY id'];
        yield 'derived key' => ['SELECT id, a FROM (SELECT * FROM t1 LIMIT 3) x GROUP BY id'];
        yield 'derived expression' => ['SELECT i2, a FROM (SELECT id, id + 1 AS i2, a FROM t1) x GROUP BY id'];
        yield 'derived grouping' => ['SELECT m FROM (SELECT a + 1 AS k, MAX(b) AS m FROM t1 GROUP BY a + 1) x GROUP BY k'];
        yield 'common table' => ['WITH x AS (SELECT * FROM t1) SELECT id, a FROM x GROUP BY id'];
        yield 'outer join' => ['SELECT t2.name FROM t1 LEFT JOIN t2 ON t1.id = t2.id GROUP BY t1.id'];
        yield 'using' => ['SELECT t2.name FROM t1 JOIN t2 USING (id) GROUP BY t1.id'];
        yield 'right join using' => ['SELECT t1.b FROM t1 RIGHT JOIN t2 USING (id) GROUP BY id'];
        yield 'natural join' => ['SELECT t2.name FROM t1 NATURAL JOIN t2 GROUP BY t1.id'];
        yield 'nested join' => ['SELECT t2.name FROM (t1 JOIN t2 ON t1.id = t2.id) GROUP BY t1.id'];
    }

    #[DataProvider('providerCheckAcceptsColumnsTheRowsDetermine')]
    public function testCheckAcceptsColumnsTheRowsDetermine(string $sql): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $tables = [
            $semantics->analyze('CREATE TABLE fz.t1 (id INT PRIMARY KEY, a INT, b VARCHAR(20))'),
            $semantics->analyze('CREATE TABLE fz.t2 (id INT PRIMARY KEY, name VARCHAR(20) NOT NULL, flag TINYINT, UNIQUE KEY uk (name))'),
        ];

        self::assertSame([], $semantics->analyze($sql, $semantics->context($tables, true, new SearchPath('fz')))->facts->diagnostics);
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
        self::assertInstanceOf(Select::class, $select);

        self::assertCount(4, (new GroupedColumns())->leaves($select->from));
    }

    public function testJoinsAnswersTheJoinsOfAFromClause(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $select = $semantics->analyze('SELECT 1 FROM (t1 JOIN t2 ON 1), { OJ t3 LEFT JOIN (SELECT 1) AS d ON 1 }')->statement;
        self::assertInstanceOf(Select::class, $select);

        self::assertCount(2, (new GroupedColumns())->joins($select->from));
    }

    public function testPartsAnswersWhatAListGroupsAndNullForATable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $select = $semantics->analyze('SELECT 1 FROM t1, t2')->statement;
        self::assertInstanceOf(Select::class, $select);
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
        self::assertInstanceOf(Select::class, $select);
        self::assertNotNull($select->from);
        $relations = [spl_object_id($select->from) => new VisibleRelation($select->from, $operation->facts->relation($select->from)->shape)];

        self::assertCount(3, (new GroupedColumns())->closure($select, $relations, $operation->facts, []));
    }

    public function testKeyedDeterminesNothingWithoutABoundKey(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE fz.t1 (id INT PRIMARY KEY, a INT, b VARCHAR(20))');
        $operation = $semantics->analyze('SELECT a FROM t1 WHERE id = 1', $semantics->context([$table], true, new SearchPath('fz')));
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        self::assertNotNull($select->from);
        $relations = [spl_object_id($select->from) => new VisibleRelation($select->from, $operation->facts->relation($select->from)->shape)];

        self::assertCount(0, (new GroupedColumns())->keyed($relations, $operation->facts, []));
    }

    public function testBlockAnswersTheQueryBlockOfADerivedTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $operation = $semantics->analyze('SELECT 1 FROM ((SELECT 1 AS a) ORDER BY a) AS d, (SELECT 1 UNION SELECT 2) AS e');
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        [$derived, $union] = (new GroupedColumns())->leaves($select->from);

        self::assertInstanceOf(Select::class, (new GroupedColumns())->block($derived, $operation->facts));
        self::assertNull((new GroupedColumns())->block($union, $operation->facts));
    }

    public function testCheckLeavesAStatementWithAProblemAlone(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');

        self::assertCount(1, $semantics->analyze('SELECT a AS x, b AS x FROM t GROUP BY x', [$table])->facts->diagnostics);
    }

    public function testGroupedReportsTheFirstOrderKeyTheGroupingDoesNotDetermine(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $context = $semantics->context([$semantics->analyze('CREATE TABLE fz.t1 (id INT PRIMARY KEY, a INT, b VARCHAR(20))')], true, new SearchPath('fz'));
        $operation = $semantics->analyze('SELECT a FROM t1 GROUP BY a ORDER BY a, b', $context);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(TableReference::class, $select->from);
        $relations = [spl_object_id($select->from) => new VisibleRelation($select->from, $operation->facts->relation($select->from)->shape, null, $select->from->name)];

        $problem = (new GroupedColumns())->grouped($select, $operation->facts->query($select)->projection, $relations, $operation->facts, new Derivation($context));

        self::assertNotNull($problem);
        self::assertSame([GroupingRule::NotDetermined, true, 2, 'fz.t1.b'], [$problem->rule, $problem->ordering, $problem->position, $problem->column]);
    }

    public function testGroupedAcceptsTheColumnsAKeyDetermines(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $context = $semantics->context([$semantics->analyze('CREATE TABLE fz.t1 (id INT PRIMARY KEY, a INT, b VARCHAR(20))')], true, new SearchPath('fz'));
        $operation = $semantics->analyze('SELECT a, b FROM t1 GROUP BY id ORDER BY b', $context);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        self::assertNotNull($select->from);
        $relations = [spl_object_id($select->from) => new VisibleRelation($select->from, $operation->facts->relation($select->from)->shape)];

        self::assertNull((new GroupedColumns())->grouped($select, $operation->facts->query($select)->projection, $relations, $operation->facts, new Derivation($context)));
    }

    public function testDistinctReportsAnOrderColumnNoSelectItemIs(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $context = $semantics->context([$semantics->analyze('CREATE TABLE fz.t1 (id INT PRIMARY KEY, a INT, b VARCHAR(20))')], true, new SearchPath('fz'));
        $operation = $semantics->analyze('SELECT DISTINCT a, a + 1 AS c FROM t1 ORDER BY a, a + 1, b', $context);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(TableReference::class, $select->from);
        $relations = [spl_object_id($select->from) => new VisibleRelation($select->from, $operation->facts->relation($select->from)->shape, null, $select->from->name)];

        $problem = (new GroupedColumns())->distinct($select, $relations, $operation->facts, new Derivation($context));

        self::assertNotNull($problem);
        self::assertSame([GroupingRule::NotSelected, true, 3, 'fz.t1.b'], [$problem->rule, $problem->ordering, $problem->position, $problem->column]);
    }

    public function testDistinctLeavesAStarSelectAlone(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $context = $semantics->context([$semantics->analyze('CREATE TABLE fz.t1 (id INT PRIMARY KEY, a INT, b VARCHAR(20))')], true, new SearchPath('fz'));
        $operation = $semantics->analyze('SELECT DISTINCT * FROM t1 ORDER BY b', $context);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        self::assertNotNull($select->from);
        $relations = [spl_object_id($select->from) => new VisibleRelation($select->from, $operation->facts->relation($select->from)->shape)];

        self::assertNull((new GroupedColumns())->distinct($select, $relations, $operation->facts, new Derivation($context)));
    }

    public function testUndeterminedAnswersTheFirstColumnOutsideTheDeterminedSet(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $context = $semantics->context([$semantics->analyze('CREATE TABLE fz.t1 (id INT PRIMARY KEY, a INT, b VARCHAR(20))')], true, new SearchPath('fz'));
        $operation = $semantics->analyze('SELECT a + b, a + 1 FROM t1 GROUP BY a + 1', $context);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        self::assertNotNull($select->from);
        self::assertNotNull($select->groupBy);
        self::assertInstanceOf(SelectExpression::class, $select->items[0]);
        self::assertInstanceOf(SelectExpression::class, $select->items[1]);
        $relations = [spl_object_id($select->from) => new VisibleRelation($select->from, $operation->facts->relation($select->from)->shape)];
        $columns = new GroupedColumns();
        $groups = [$select->groupBy->items[0]->expression];
        $a = $columns->columns($select->items[0]->expression, $relations, $operation->facts, false)[0][0];

        self::assertSame('a', $columns->undetermined($select->items[0]->expression, $groups, [], $relations, $operation->facts)?->slot->name?->value);
        self::assertSame('b', $columns->undetermined($select->items[0]->expression, $groups, [$a => true], $relations, $operation->facts)?->slot->name?->value);
        self::assertNull($columns->undetermined($select->items[1]->expression, $groups, [], $relations, $operation->facts));
    }

    public function testListedFindsAnExpressionWrittenAsAListedOne(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $context = $semantics->context([$semantics->analyze('CREATE TABLE fz.t1 (id INT PRIMARY KEY, a INT, b VARCHAR(20))')], true, new SearchPath('fz'));
        $operation = $semantics->analyze('SELECT (A), t1.a, b FROM t1', $context);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(SelectExpression::class, $select->items[0]);
        self::assertInstanceOf(SelectExpression::class, $select->items[1]);
        self::assertInstanceOf(SelectExpression::class, $select->items[2]);
        $columns = new GroupedColumns();

        self::assertTrue($columns->listed($select->items[1]->expression, [$select->items[2]->expression, $select->items[0]->expression], $operation->facts));
        self::assertFalse($columns->listed($select->items[1]->expression, [$select->items[0]->expression]));
        self::assertFalse($columns->listed($select->items[2]->expression, [$select->items[0]->expression], $operation->facts));
        self::assertFalse($columns->listed($select->items[0]->expression, []));
    }

    public function testTargetAnswersTheSelectItemAnAliasNames(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $context = $semantics->context([$semantics->analyze('CREATE TABLE fz.t1 (id INT PRIMARY KEY, a INT, b VARCHAR(20))')], true, new SearchPath('fz'));
        $operation = $semantics->analyze('SELECT a + 1 AS x FROM t1 ORDER BY (x), b', $context);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(SelectExpression::class, $select->items[0]);
        $columns = new GroupedColumns();

        self::assertSame($select->items[0]->expression, $columns->target($select->orderBy[0]->expression, $operation->facts));
        self::assertSame($select->orderBy[1]->expression, $columns->target($select->orderBy[1]->expression, $operation->facts));
    }

    public function testUnwrapRemovesEveryPairOfParentheses(): void
    {
        $select = (new Semantics(Dialect::MySql))->analyze('SELECT ((a)), b FROM t1')->statement;
        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(SelectExpression::class, $select->items[0]);
        self::assertInstanceOf(SelectExpression::class, $select->items[1]);
        $columns = new GroupedColumns();

        $unwrapped = $columns->unwrap($select->items[0]->expression);
        self::assertInstanceOf(ColumnUse::class, $unwrapped);
        self::assertSame('a', $unwrapped->name->value);
        self::assertSame($select->items[1]->expression, $columns->unwrap($select->items[1]->expression));
    }

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
        $columns = new GroupedColumns();

        self::assertSame(['a'], array_map(static fn (array $found): ?string => $found[1]->slot->name?->value, $columns->columns($select->items[0]->expression, $relations, $operation->facts, false)));
        self::assertSame(['a', 'b', 'id'], array_map(static fn (array $found): ?string => $found[1]->slot->name?->value, $columns->columns($select->items[0]->expression, $relations, $operation->facts, true)));
        self::assertSame([], $columns->columns($select->items[0]->expression, [], $operation->facts, true));
    }

    public function testConjunctsAnswersTheAndOperandsOfACondition(): void
    {
        $select = (new Semantics(Dialect::MySql))->analyze('SELECT 1 FROM t1 WHERE (a = 1 AND b = 2) AND (id = 3 OR a = 4)')->statement;
        self::assertInstanceOf(Select::class, $select);
        $columns = new GroupedColumns();

        self::assertSame([Comparison::class, Comparison::class, Logical::class], array_map(static fn (object $conjunct): string => $conjunct::class, $columns->conjuncts($select->where)));
        self::assertSame([], $columns->conjuncts(null));
    }

    public function testSameComparesTwoColumnsOfOneOccurrenceAsOneWithFacts(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $context = $semantics->context([$semantics->analyze('CREATE TABLE fz.t1 (id INT PRIMARY KEY, a INT, b VARCHAR(20))')], true, new SearchPath('fz'));
        $operation = $semantics->analyze('SELECT t1.a + 1, a + 1, a + 2 FROM t1', $context);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(SelectExpression::class, $select->items[0]);
        self::assertInstanceOf(SelectExpression::class, $select->items[1]);
        self::assertInstanceOf(SelectExpression::class, $select->items[2]);
        $columns = new GroupedColumns();

        self::assertTrue($columns->same($select->items[0]->expression, $select->items[1]->expression, $operation->facts));
        self::assertFalse($columns->same($select->items[0]->expression, $select->items[1]->expression));
        self::assertFalse($columns->same($select->items[1]->expression, $select->items[2]->expression, $operation->facts));
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
