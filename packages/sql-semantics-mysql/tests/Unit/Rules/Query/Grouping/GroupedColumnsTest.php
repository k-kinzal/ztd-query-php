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
use SqlSemantics\Platform\MySql\Statement\Query\Problem\GroupingRule;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\NonGroupedColumn;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

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

    public function testNameWritesTheDatabaseTheTableAndTheColumn(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $context = $semantics->context([$semantics->analyze('CREATE TABLE fz.t1 (id INT PRIMARY KEY, a INT, b VARCHAR(20))')], true, new SearchPath('fz'));
        $operation = $semantics->analyze('SELECT a FROM t1', $context);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(TableReference::class, $select->from);
        self::assertInstanceOf(SelectExpression::class, $select->items[0]);
        $column = $operation->facts->scalar($select->items[0]->expression)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $column);
        $shape = $operation->facts->relation($select->from)->shape;
        $columns = new GroupedColumns();

        self::assertSame('fz.t1.a', $columns->name($column, [spl_object_id($select->from) => new VisibleRelation($select->from, $shape, null, $select->from->name)], new Derivation($context)));
        self::assertSame('x.a', $columns->name($column, [spl_object_id($select->from) => new VisibleRelation($select->from, $shape, new Name('x'))], new Derivation($context)));
        self::assertSame('.a', $columns->name($column, [], new Derivation($context)));
    }
}
