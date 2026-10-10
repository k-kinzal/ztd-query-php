<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Grouping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\SearchPath;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Query\Grouping\BlockParts;
use SqlSemantics\Platform\MySql\Rules\Query\Grouping\ColumnReads;
use SqlSemantics\Platform\MySql\Rules\Query\Grouping\Determination;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Shape\Field;

#[CoversClass(Determination::class)]
#[Medium]
final class DeterminationTest extends TestCase
{
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

    public function testJoinedComparesTheColumnsUsingAndNaturalName(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $context = $semantics->context([$semantics->analyze('CREATE TABLE fz.t1 (id INT PRIMARY KEY, a INT, b VARCHAR(20))'), $semantics->analyze('CREATE TABLE fz.t2 (id INT PRIMARY KEY, name VARCHAR(20) NOT NULL, a INT, UNIQUE KEY uk (name))')], true, new SearchPath('fz'));

        self::assertSame(0, count($semantics->analyze('SELECT t2.name FROM t1 JOIN t2 USING (id) GROUP BY t1.id', $context)->facts->diagnostics));
        self::assertSame(0, count($semantics->analyze('SELECT t2.name FROM t1 NATURAL JOIN t2 GROUP BY t1.id', $context)->facts->diagnostics));
    }

    public function testJoinedDeterminesTheInnerSideByAValue(): void
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

        self::assertCount(3, (new Determination())->closure($select, $relations, $operation->facts, []));
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

        self::assertCount(0, (new Determination())->keyed($relations, $operation->facts, []));
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
        $columns = new Determination();
        $groups = [$select->groupBy->items[0]->expression];
        $a = (new ColumnReads())->columns($select->items[0]->expression, $relations, $operation->facts, false)[0][0];

        self::assertSame('a', $columns->undetermined($select->items[0]->expression, $groups, [], $relations, $operation->facts)?->slot->name?->value);
        self::assertSame('b', $columns->undetermined($select->items[0]->expression, $groups, [$a => true], $relations, $operation->facts)?->slot->name?->value);
        self::assertNull($columns->undetermined($select->items[1]->expression, $groups, [], $relations, $operation->facts));
    }

    public function testFieldsAnswersTheFieldEachColumnOfADerivedTableShows(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $context = $semantics->context([$semantics->analyze('CREATE TABLE fz.t1 (id INT PRIMARY KEY, a INT, b VARCHAR(20))')], true, new SearchPath('fz'));
        $operation = $semantics->analyze('SELECT d.id FROM (SELECT id, a + 1 AS c FROM t1) AS d', $context);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(DerivedTable::class, $select->from);
        $inner = (new BlockParts())->block($select->from, $operation->facts);
        self::assertNotNull($inner);
        $relation = new VisibleRelation($select->from, $operation->facts->relation($select->from)->shape);
        $projection = $operation->facts->query($inner)->projection;

        self::assertSame($projection, (new Determination())->fields($relation, $projection));
        self::assertNull((new Determination())->fields($relation, [$projection[0]]));
    }

    public function testShownAnswersTheColumnASelectItemOrAStarNames(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $context = $semantics->context([$semantics->analyze('CREATE TABLE fz.t1 (id INT PRIMARY KEY, a INT)')], true, new SearchPath('fz'));
        $operation = $semantics->analyze('SELECT (id), id + 1, t1.* FROM t1', $context);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $occurrences = (new BlockParts())->occurrences($select, $operation->facts);
        [$grouped, $expression, $id, $a] = $operation->facts->query($select)->projection;
        self::assertInstanceOf(Field::class, $grouped);
        self::assertInstanceOf(Field::class, $expression);
        self::assertInstanceOf(Field::class, $id);
        self::assertInstanceOf(Field::class, $a);
        $determination = new Determination();

        self::assertSame(['id', 'id', 'a'], [$determination->shown($grouped, $occurrences, $operation->facts)[0][1]->slot->name?->value, $determination->shown($id, $occurrences, $operation->facts)[0][1]->slot->name?->value, $determination->shown($a, $occurrences, $operation->facts)[0][1]->slot->name?->value]);
        self::assertSame([], $determination->shown($expression, $occurrences, $operation->facts));
        self::assertSame([], $determination->shown($a, [], $operation->facts));
    }

    public function testGroupedHoldsWhenEveryGroupByExpressionIsDetermined(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $context = $semantics->context([$semantics->analyze('CREATE TABLE fz.t1 (id INT PRIMARY KEY, a INT, b VARCHAR(20))')], true, new SearchPath('fz'));
        $operation = $semantics->analyze('SELECT a + 1 AS k, MAX(b) FROM t1 GROUP BY k', $context);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(SelectExpression::class, $select->items[0]);
        $occurrences = (new BlockParts())->occurrences($select, $operation->facts);
        $a = (new ColumnReads())->columns($select->items[0]->expression, $occurrences, $operation->facts, false)[0][0];
        $determination = new Determination();

        self::assertSame([true, false, true], [
            $determination->grouped($select, [$select->items[0]->expression], [], $occurrences, $operation->facts),
            $determination->grouped($select, [], [], $occurrences, $operation->facts),
            $determination->grouped($select, [], [$a => true], $occurrences, $operation->facts),
        ]);
        self::assertFalse($determination->grouped(new Select([], [new SelectExpression(new NumberLiteral('1'))]), [], [], [], $operation->facts));
    }
}
