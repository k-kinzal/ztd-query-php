<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Value;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Query\SetOperator;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Value\SetColumn;
use SqlSemantics\Model\Statement\CompoundStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(SetColumn::class)]
#[Medium]
final class SetColumnTest extends TestCase
{
    public function testInputsRetainsEachBranchOutput(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('SELECT 1 UNION ALL SELECT NULL');
        self::assertInstanceOf(CompoundStatement::class, $statement);
        $column = $statement->outputs[0]->expression;
        self::assertInstanceOf(SetColumn::class, $column);
        self::assertSame(SetOperator::UnionAll, $column->operator);
        self::assertCount(2, $column->alternatives);
        self::assertSame($column->alternatives, $column->inputs());
        self::assertSame('1', $column->alternatives[0]->spelling());
        self::assertSame('integer', $column->type->name);
        self::assertSame(Nullability::MaybeNull, $column->nullability);
        self::assertSame('SELECT 1 UNION ALL SELECT NULL', $statement->toString());
        self::assertSame('SELECT 1 UNION ALL SELECT NULL', $binder->bind($statement->toString())->toString());
    }

    public function testSpellingReturnsTheSetOperator(): void
    {
        $left = Expression::literal(1, Dialect::PostgreSql);
        $column = new SetColumn($left->facts, $left->source, SetOperator::Except, [$left, Expression::literal(2, Dialect::PostgreSql)]);
        self::assertSame('EXCEPT', $column->spelling());
    }

    public function testRejectsAlternativesFromAnotherDialect(): void
    {
        $left = Expression::literal(1, Dialect::PostgreSql);
        $this->expectException(InvalidStructure::class);
        new SetColumn($left->facts, $left->source, SetOperator::Union, [$left, Expression::literal(2, Dialect::Sqlite)]);
    }

    public function testWithFactsKeepsTheOperatorAndAlternatives(): void
    {
        $left = Expression::literal(1, Dialect::PostgreSql);
        $right = Expression::literal(2, Dialect::PostgreSql);
        $column = new SetColumn($left->facts, $left->source, SetOperator::Intersect, [$left, $right]);
        $copy = $column->withFacts(new ExpressionFacts($column->type, Nullability::MaybeNull));
        self::assertNotSame($column, $copy);
        self::assertSame(SetOperator::Intersect, $copy->operator);
        self::assertSame([$left, $right], $copy->alternatives);
        self::assertSame(Nullability::MaybeNull, $copy->nullability);
        self::assertSame(Nullability::NotNull, $column->nullability);
    }
}
