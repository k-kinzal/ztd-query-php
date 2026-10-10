<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Grouping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\SearchPath;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Query\Grouping\Matching;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Matching::class)]
#[Medium]
final class MatchingTest extends TestCase
{
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
        $columns = new Matching();

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
        $columns = new Matching();

        self::assertSame($select->items[0]->expression, $columns->target($select->orderBy[0]->expression, $operation->facts));
        self::assertSame($select->orderBy[1]->expression, $columns->target($select->orderBy[1]->expression, $operation->facts));
    }

    public function testUnwrapRemovesEveryPairOfParentheses(): void
    {
        $select = (new Semantics(Dialect::MySql))->analyze('SELECT ((a)), b FROM t1')->statement;
        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(SelectExpression::class, $select->items[0]);
        self::assertInstanceOf(SelectExpression::class, $select->items[1]);
        $columns = new Matching();

        $unwrapped = $columns->unwrap($select->items[0]->expression);
        self::assertInstanceOf(ColumnUse::class, $unwrapped);
        self::assertSame('a', $unwrapped->name->value);
        self::assertSame($select->items[1]->expression, $columns->unwrap($select->items[1]->expression));
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
        $columns = new Matching();

        self::assertTrue($columns->same($select->items[0]->expression, $select->items[1]->expression, $operation->facts));
        self::assertFalse($columns->same($select->items[0]->expression, $select->items[1]->expression));
        self::assertFalse($columns->same($select->items[1]->expression, $select->items[2]->expression, $operation->facts));
    }

    public function testSameComparesNamesWithoutRegardToCase(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $left = $semantics->analyze('SELECT A FROM t')->statement;
        $right = $semantics->analyze('SELECT a FROM t')->statement;

        self::assertTrue((new Matching())->same($left, $right));
        self::assertFalse((new Matching())->same($left, $semantics->analyze('SELECT b FROM t')->statement));
    }

    public function testAlikeComparesClassesEnumCasesAndNamesAndLeavesTheRestToTheProperties(): void
    {
        $matching = new Matching();

        self::assertSame([true, false, true, false, false], [
            $matching->alike(new Name('A'), new Name('a'), null),
            $matching->alike(new Name('a'), new Name('b'), null),
            $matching->alike(ComparisonOperator::Equal, ComparisonOperator::Equal, null),
            $matching->alike(ComparisonOperator::Equal, ComparisonOperator::NullSafeEqual, null),
            $matching->alike(new Name('a'), new ColumnUse(new Name('a')), null),
        ]);
        self::assertNull($matching->alike(new ColumnUse(new Name('a')), new ColumnUse(new Name('a')), null));
    }

    public function testColumnComparesTheColumnsTheFactsResolve(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $context = $semantics->context([$semantics->analyze('CREATE TABLE fz.t1 (id INT PRIMARY KEY, a INT, b VARCHAR(20))')], true, new SearchPath('fz'));
        $operation = $semantics->analyze('SELECT t1.a, a, b FROM t1', $context);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(SelectExpression::class, $select->items[0]);
        self::assertInstanceOf(SelectExpression::class, $select->items[1]);
        self::assertInstanceOf(SelectExpression::class, $select->items[2]);
        self::assertInstanceOf(ColumnUse::class, $select->items[0]->expression);
        self::assertInstanceOf(ColumnUse::class, $select->items[1]->expression);
        self::assertInstanceOf(ColumnUse::class, $select->items[2]->expression);
        $matching = new Matching();

        self::assertTrue($matching->column($select->items[0]->expression, $select->items[1]->expression, $operation->facts));
        self::assertFalse($matching->column($select->items[1]->expression, $select->items[2]->expression, $operation->facts));
        self::assertNull($matching->column($select->items[0]->expression, $select->items[1]->expression, null));
        self::assertNull($matching->column($select->items[0]->expression, new ColumnUse(new Name('a')), $operation->facts));
    }
}
