<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Query\InSubquery;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(InSubquery::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class InSubqueryTest extends TestCase
{
    public function testRejectsAQueryWithTheWrongOperandWidth(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('SELECT 1 IN (SELECT 1)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $query = $binder->bind('SELECT 1, 2');
        self::assertInstanceOf(BoundSelect::class, $query);


        $expression = $statement->outputs[0]->expression;
        self::assertInstanceOf(InSubquery::class, $expression);
        $this->expectException(InvalidStructure::class);
        new InSubquery($expression->facts, $expression->source, $expression->value, $query, false);
    }

    public function testSubqueryRetainsTheComparisonInput(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1 IN (SELECT 1)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $expression = $statement->outputs[0]->expression;
        self::assertInstanceOf(InSubquery::class, $expression);
        self::assertSame($expression->query, $expression->subquery());
        self::assertSame('1', $expression->inputs()[0]->spelling());
        self::assertSame($expression->type, $expression->withFacts($expression->facts)->type);
    }


    public function testInputsStartWithTheComparedValueThenTheResultColumns(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1 IN (SELECT 2)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $expression = $statement->outputs[0]->expression;
        self::assertInstanceOf(InSubquery::class, $expression);
        $inputs = $expression->inputs();
        self::assertCount(2, $inputs);
        self::assertSame($expression->value, $inputs[0]);
        self::assertSame($expression->query->resultColumns()[0]->expression, $inputs[1]);
        self::assertSame('2', $inputs[1]->spelling());
    }

    public function testSpellingReflectsNegation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1 IN (SELECT 1), 1 NOT IN (SELECT 1)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $positive = $statement->outputs[0]->expression;
        self::assertInstanceOf(InSubquery::class, $positive);
        $negative = $statement->outputs[1]->expression;
        self::assertInstanceOf(InSubquery::class, $negative);
        self::assertFalse($positive->negated);
        self::assertSame('IN', $positive->spelling());
        self::assertTrue($negative->negated);
        self::assertSame('NOT IN', $negative->spelling());
    }

    public function testWithFactsReplacesTheFactsAndKeepsEveryOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1 NOT IN (SELECT 1)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $expression = $statement->outputs[0]->expression;
        self::assertInstanceOf(InSubquery::class, $expression);
        $changed = $expression->withFacts(new ExpressionFacts($expression->type, Nullability::AlwaysNull, ['j0']));
        self::assertNotSame($expression, $changed);
        self::assertSame(Nullability::AlwaysNull, $changed->nullability);
        self::assertSame(['j0'], $changed->nullExtendedBy);
        self::assertNotSame(Nullability::AlwaysNull, $expression->nullability);
        self::assertSame([], $expression->nullExtendedBy);
        self::assertSame($expression->value, $changed->value);
        self::assertSame($expression->query, $changed->query);
        self::assertTrue($changed->negated);
    }
}
