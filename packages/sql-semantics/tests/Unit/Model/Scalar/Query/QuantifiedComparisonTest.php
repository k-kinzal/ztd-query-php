<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Query\ComparisonOperator;
use SqlSemantics\Model\Scalar\Query\QuantifiedComparison;
use SqlSemantics\Model\Scalar\Query\Quantifier;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(QuantifiedComparison::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class QuantifiedComparisonTest extends TestCase
{
    public function testRejectsAQueryWithTheWrongOperandWidth(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('SELECT 1 = ANY (SELECT 1)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $query = $binder->bind('SELECT 1, 2');
        self::assertInstanceOf(BoundSelect::class, $query);


        $expression = $statement->outputs[0]->expression;
        self::assertInstanceOf(QuantifiedComparison::class, $expression);
        $this->expectException(InvalidStructure::class);
        new QuantifiedComparison($expression->facts, $expression->source, $expression->value, $expression->operator, $expression->quantifier, $query);
    }

    public function testSubqueryRetainsTheComparisonInput(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1 = ANY (SELECT 1)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $expression = $statement->outputs[0]->expression;
        self::assertInstanceOf(QuantifiedComparison::class, $expression);
        self::assertSame($expression->query, $expression->subquery());
        self::assertSame('1', $expression->inputs()[0]->spelling());
        self::assertSame($expression->type, $expression->withFacts($expression->facts)->type);
    }


    public function testInputsStartWithTheComparedValueThenTheResultColumns(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1 < ANY (SELECT 2)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $expression = $statement->outputs[0]->expression;
        self::assertInstanceOf(QuantifiedComparison::class, $expression);
        $inputs = $expression->inputs();
        self::assertCount(2, $inputs);
        self::assertSame($expression->value, $inputs[0]);
        self::assertSame($expression->query->resultColumns()[0]->expression, $inputs[1]);
        self::assertSame('2', $inputs[1]->spelling());
    }

    public function testSpellingCombinesTheOperatorAndQuantifier(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1 <> ALL (SELECT 1), 1 = SOME (SELECT 1)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $all = $statement->outputs[0]->expression;
        self::assertInstanceOf(QuantifiedComparison::class, $all);
        $some = $statement->outputs[1]->expression;
        self::assertInstanceOf(QuantifiedComparison::class, $some);
        self::assertSame(ComparisonOperator::NotEqual, $all->operator);
        self::assertSame(Quantifier::All, $all->quantifier);
        self::assertSame('<> ALL', $all->spelling());
        self::assertSame(ComparisonOperator::Equal, $some->operator);
        self::assertSame(Quantifier::Some, $some->quantifier);
        self::assertSame('= SOME', $some->spelling());
    }

    public function testWithFactsKeepsTheOperatorQuantifierAndQuery(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1 >= ALL (SELECT 1)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $expression = $statement->outputs[0]->expression;
        self::assertInstanceOf(QuantifiedComparison::class, $expression);
        $changed = $expression->withFacts(new ExpressionFacts($expression->type, Nullability::AlwaysNull, ['j0']));
        self::assertNotSame($expression, $changed);
        self::assertSame(Nullability::AlwaysNull, $changed->nullability);
        self::assertSame(['j0'], $changed->nullExtendedBy);
        self::assertSame([], $expression->nullExtendedBy);
        self::assertSame($expression->value, $changed->value);
        self::assertSame(ComparisonOperator::GreaterEqual, $changed->operator);
        self::assertSame(Quantifier::All, $changed->quantifier);
        self::assertSame($expression->query, $changed->query);
    }

    public function testSpellingNamesAPatternOrNamedOperator(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SELECT 'a' NOT ILIKE ANY (SELECT 'b'), 1 OPERATOR(geo.<->) ALL (SELECT 2)");
        self::assertInstanceOf(BoundSelect::class, $statement);
        $pattern = $statement->outputs[0]->expression;
        self::assertInstanceOf(QuantifiedComparison::class, $pattern);
        self::assertSame(\SqlSemantics\Model\Scalar\Conditional\PatternOperator::ILike, $pattern->operator);
        self::assertTrue($pattern->negated);
        self::assertSame('NOT ILIKE ANY', $pattern->spelling());
        self::assertTrue($pattern->withFacts($pattern->facts)->negated);
        $named = $statement->outputs[1]->expression;
        self::assertInstanceOf(QuantifiedComparison::class, $named);
        self::assertSame('OPERATOR(geo.<->) ALL', $named->spelling());
    }

    public function testRejectsANegatedComparisonOrAPatternOutsidePostgreSql(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1 = ANY (SELECT 1)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $expression = $statement->outputs[0]->expression;
        self::assertInstanceOf(QuantifiedComparison::class, $expression);
        $this->expectException(InvalidStructure::class);
        new QuantifiedComparison($expression->facts, $expression->source, $expression->value, ComparisonOperator::Equal, Quantifier::Any, $expression->query, true);
    }

    public function testRejectsARegularExpressionPattern(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1 = ANY (SELECT 1)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $expression = $statement->outputs[0]->expression;
        self::assertInstanceOf(QuantifiedComparison::class, $expression);
        $this->expectException(InvalidStructure::class);
        new QuantifiedComparison($expression->facts, $expression->source, $expression->value, \SqlSemantics\Model\Scalar\Conditional\PatternOperator::Regexp, Quantifier::Any, $expression->query);
    }
}
