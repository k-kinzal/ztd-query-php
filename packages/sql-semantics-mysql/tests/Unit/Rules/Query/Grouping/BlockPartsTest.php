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
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\Logical;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\TableList;
use SqlSemantics\Resolution\VisibleRelation;

#[CoversClass(BlockParts::class)]
#[Medium]
final class BlockPartsTest extends TestCase
{
    public function testLeavesAnswersTheTablesOfAFromClauseThroughJoinsListsAndParentheses(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $select = $semantics->analyze('SELECT 1 FROM (t1 JOIN t2 ON 1), { OJ t3 LEFT JOIN (SELECT 1) AS d ON 1 }')->statement;
        self::assertInstanceOf(Select::class, $select);

        self::assertCount(4, (new BlockParts())->leaves($select->from));
    }

    public function testJoinsAnswersTheJoinsOfAFromClause(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $select = $semantics->analyze('SELECT 1 FROM (t1 JOIN t2 ON 1), { OJ t3 LEFT JOIN (SELECT 1) AS d ON 1 }')->statement;
        self::assertInstanceOf(Select::class, $select);

        self::assertCount(2, (new BlockParts())->joins($select->from));
    }

    public function testPartsAnswersWhatAListGroupsAndNullForATable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $select = $semantics->analyze('SELECT 1 FROM t1, t2')->statement;
        self::assertInstanceOf(Select::class, $select);
        $columns = new BlockParts();

        self::assertCount(2, $columns->parts($select->from) ?? []);
        self::assertNull($columns->parts($columns->leaves($select->from)[0]));
    }

    public function testBlockAnswersTheQueryBlockOfADerivedTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $operation = $semantics->analyze('SELECT 1 FROM ((SELECT 1 AS a) ORDER BY a) AS d, (SELECT 1 UNION SELECT 2) AS e');
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        [$derived, $union] = (new BlockParts())->leaves($select->from);

        self::assertInstanceOf(Select::class, (new BlockParts())->block($derived, $operation->facts));
        self::assertNull((new BlockParts())->block($union, $operation->facts));
    }

    public function testConjunctsAnswersTheAndOperandsOfACondition(): void
    {
        $select = (new Semantics(Dialect::MySql))->analyze('SELECT 1 FROM t1 WHERE (a = 1 AND b = 2) AND (id = 3 OR a = 4)')->statement;
        self::assertInstanceOf(Select::class, $select);
        $columns = new BlockParts();

        self::assertSame([Comparison::class, Comparison::class, Logical::class], array_map(static fn (object $conjunct): string => $conjunct::class, $columns->conjuncts($select->where)));
        self::assertSame([], $columns->conjuncts(null));
    }

    public function testMembersAnswersTheGivenOccurrencesOfAPart(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $context = $semantics->context([$semantics->analyze('CREATE TABLE fz.t1 (id INT PRIMARY KEY, a INT)'), $semantics->analyze('CREATE TABLE fz.t2 (id INT PRIMARY KEY)')], true, new SearchPath('fz'));
        $operation = $semantics->analyze('SELECT 1 FROM t1 JOIN t2 ON t1.id = t2.id, t1 AS x', $context);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(TableList::class, $select->from);
        self::assertInstanceOf(JoinedTable::class, $select->from->members[0]);
        $parts = new BlockParts();
        $occurrences = $parts->occurrences($select, $operation->facts);
        $join = $select->from->members[0];

        self::assertSame([spl_object_id($join->left), spl_object_id($join->right)], array_keys($parts->members($join, $occurrences)));
        self::assertSame([spl_object_id($join->right)], array_keys($parts->members($join, array_diff_key($occurrences, [spl_object_id($join->left) => true]))));
    }

    public function testOccurrencesAnswersTheTablesOfTheFromClauseWithTheirShapes(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $context = $semantics->context([$semantics->analyze('CREATE TABLE fz.t1 (id INT PRIMARY KEY, a INT)'), $semantics->analyze('CREATE TABLE fz.t2 (id INT PRIMARY KEY)')], true, new SearchPath('fz'));
        $operation = $semantics->analyze('SELECT 1 FROM t1 JOIN t2 ON t1.id = t2.id, t1 AS x', $context);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $parts = new BlockParts();

        $occurrences = $parts->occurrences($select, $operation->facts);

        self::assertSame(array_map('spl_object_id', $parts->leaves($select->from)), array_keys($occurrences));
        self::assertSame([2, 1, 2], array_map(static fn (VisibleRelation $relation): int => count($relation->shape->slots), array_values($occurrences)));
        self::assertSame([], $parts->occurrences(new Select([], [new SelectExpression(new NumberLiteral('1'))]), $operation->facts));
    }
}
