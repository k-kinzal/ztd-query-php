<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Value;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Value\ValuesColumn;
use SqlSemantics\Model\Statement\ValuesStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(ValuesColumn::class)]
#[Medium]
final class ValuesColumnTest extends TestCase
{
    public function testInputsRetainsOneAlternativePerRow(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('VALUES (1), (NULL)');
        self::assertInstanceOf(ValuesStatement::class, $statement);
        $column = $statement->outputs[0]->expression;
        self::assertInstanceOf(ValuesColumn::class, $column);
        self::assertCount(2, $column->alternatives);
        self::assertSame($column->alternatives, $column->inputs());
        self::assertSame('NULL', $column->alternatives[1]->spelling());
        self::assertSame('integer', $column->type->name);
        self::assertSame(Nullability::MaybeNull, $column->nullability);
        self::assertSame('VALUES (1), (NULL)', $statement->toString());
        self::assertSame('VALUES (1), (NULL)', $binder->bind($statement->toString())->toString());
    }

    public function testSpellingIsTheValuesKeyword(): void
    {
        $first = Expression::literal(1, Dialect::PostgreSql);
        $column = new ValuesColumn($first->facts, $first->source, [$first, Expression::literal(2, Dialect::PostgreSql)]);
        self::assertSame('VALUES', $column->spelling());
    }

    public function testRejectsAlternativesFromAnotherDialect(): void
    {
        $first = Expression::literal(1, Dialect::PostgreSql);
        $this->expectException(InvalidStructure::class);
        new ValuesColumn($first->facts, $first->source, [$first, Expression::literal(2, Dialect::MySql)]);
    }

    public function testWithFactsKeepsTheAlternatives(): void
    {
        $first = Expression::literal(1, Dialect::PostgreSql);
        $second = Expression::literal(2, Dialect::PostgreSql);
        $column = new ValuesColumn($first->facts, $first->source, [$first, $second]);
        $copy = $column->withFacts(new ExpressionFacts($column->type, Nullability::MaybeNull));
        self::assertNotSame($column, $copy);
        self::assertSame([$first, $second], $copy->alternatives);
        self::assertSame(Nullability::MaybeNull, $copy->nullability);
        self::assertSame(Nullability::NotNull, $column->nullability);
    }
}
