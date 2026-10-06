<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Lowering\Expression\CallRule;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\FunctionCall;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\BinaryOperator;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Window\WindowSpec;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortDirection;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\SetQuantifier;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(CallRule::class)]
#[Medium]
final class CallRuleTest extends TestCase
{
    public function testExpressionLowersTheArgumentsTheStarFormAndTheQuantifier(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT count(*), count(DISTINCT a), count(ALL a), max(a, b), random() FROM t');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertSame([[0, true, null], [1, false, SetQuantifier::Distinct], [1, false, SetQuantifier::All], [2, false, null], [0, false, null]], array_map(static function (object $column): array {
            self::assertInstanceOf(ResultColumn::class, $column);
            self::assertInstanceOf(FunctionCall::class, $column->expression);
            self::assertSame([], $column->expression->order);
            self::assertNull($column->expression->filter);
            self::assertNull($column->expression->over);

            return [count($column->expression->arguments), $column->expression->star, $column->expression->quantifier];
        }, $operation->statement->columns));
        self::assertSame('SELECT count(*), count(DISTINCT a), count(ALL a), max(a, b), random() FROM t', $operation->toString());
    }

    public function testExpressionKeepsTheArgumentOrderingOfAnAggregate(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze("SELECT group_concat(a ORDER BY b DESC), group_concat(a, ',' ORDER BY b, c) FROM t");

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(ResultColumn::class, $operation->statement->columns[0]);
        self::assertInstanceOf(ResultColumn::class, $operation->statement->columns[1]);
        $single = $operation->statement->columns[0]->expression;
        $double = $operation->statement->columns[1]->expression;
        self::assertInstanceOf(FunctionCall::class, $single);
        self::assertInstanceOf(FunctionCall::class, $double);
        self::assertCount(1, $single->order);
        self::assertSame(SortDirection::Descending, $single->order[0]->direction);
        self::assertInstanceOf(ColumnUse::class, $single->order[0]->expression);
        self::assertSame('b', $single->order[0]->expression->name->value);
        self::assertCount(2, $double->arguments);
        self::assertCount(2, $double->order);
        self::assertSame("SELECT group_concat(a ORDER BY b DESC), group_concat(a, ',' ORDER BY b, c) FROM t", $operation->toString());
    }

    public function testNameKeepsTheFunctionNameAsWritten(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT Count(*) AS c1, "max"(a) AS c2, [min](a) AS c3 FROM t');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertSame(['Count', 'max', 'min'], array_map(static function (object $column): string {
            self::assertInstanceOf(ResultColumn::class, $column);
            self::assertInstanceOf(FunctionCall::class, $column->expression);

            return $column->expression->name->value;
        }, $operation->statement->columns));
        self::assertSame('SELECT Count(*) AS c1, max(a) AS c2, min(a) AS c3 FROM t', $operation->toString());
    }

    public function testFilterOverLowersAFilterOnlyAWindowOnlyAndBoth(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT sum(a) FILTER (WHERE b), sum(a) OVER (), sum(a) FILTER (WHERE b) OVER w, count(*) FILTER (WHERE b) OVER (), group_concat(a ORDER BY b) OVER w FROM t WINDOW w AS ()');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertSame([[true, null], [false, WindowSpec::class], [true, Name::class], [true, WindowSpec::class], [false, Name::class]], array_map(static function (object $column): array {
            self::assertInstanceOf(ResultColumn::class, $column);
            self::assertInstanceOf(FunctionCall::class, $column->expression);

            return [$column->expression->filter !== null, $column->expression->over === null ? null : $column->expression->over::class];
        }, $operation->statement->columns));
        self::assertSame('SELECT sum(a) FILTER (WHERE b), sum(a) OVER (), sum(a) FILTER (WHERE b) OVER w, count(*) FILTER (WHERE b) OVER (), group_concat(a ORDER BY b) OVER w FROM t WINDOW w AS ()', $operation->toString());
    }

    public function testFilterLowersThePredicateOfTheFilterClause(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT sum(a) FILTER (WHERE b > 1) FROM t');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(ResultColumn::class, $operation->statement->columns[0]);
        self::assertInstanceOf(FunctionCall::class, $operation->statement->columns[0]->expression);
        $filter = $operation->statement->columns[0]->expression->filter;
        self::assertInstanceOf(Binary::class, $filter);
        self::assertSame(BinaryOperator::Greater, $filter->operator);
        self::assertInstanceOf(ColumnUse::class, $filter->left);
        self::assertSame('b', $filter->left->name->value);
    }

    public function testOverLowersAWindowSpecificationOrAWindowName(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT sum(a) OVER (PARTITION BY b ORDER BY c), sum(a) OVER w FROM t WINDOW w AS (ORDER BY c)');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(ResultColumn::class, $operation->statement->columns[0]);
        self::assertInstanceOf(ResultColumn::class, $operation->statement->columns[1]);
        self::assertInstanceOf(FunctionCall::class, $operation->statement->columns[0]->expression);
        self::assertInstanceOf(FunctionCall::class, $operation->statement->columns[1]->expression);
        $specification = $operation->statement->columns[0]->expression->over;
        $named = $operation->statement->columns[1]->expression->over;
        self::assertInstanceOf(WindowSpec::class, $specification);
        self::assertCount(1, $specification->partition);
        self::assertCount(1, $specification->order);
        self::assertNull($specification->base);
        self::assertInstanceOf(Name::class, $named);
        self::assertSame('w', $named->value);
    }
}
