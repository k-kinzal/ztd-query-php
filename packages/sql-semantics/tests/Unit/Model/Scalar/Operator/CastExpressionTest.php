<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Conditional\Coalesce;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Operator\CastExpression;
use SqlSemantics\Model\Scalar\Operator\CastMode;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(CastExpression::class)]
#[Medium]
final class CastExpressionTest extends TestCase
{
    public function testInputsContainsOnlyTheConvertedOperand(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('SELECT CAST(1 AS TEXT)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $cast = $statement->outputs[0]->expression;
        self::assertInstanceOf(CastExpression::class, $cast);
        self::assertSame(CastMode::Explicit, $cast->mode);
        self::assertSame('1', $cast->operand->spelling());
        self::assertSame([$cast->operand], $cast->inputs());
        self::assertSame('text', $cast->type->name);
        self::assertSame('integer', $cast->operand->type->name);
        self::assertSame('SELECT CAST(1 AS text)', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame('SELECT CAST(1 AS text)', (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testInputsOfAnImplicitConversionSerializeWithoutCastSyntax(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT COALESCE(NULL, 1)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $value = $statement->outputs[0]->expression;
        self::assertInstanceOf(Coalesce::class, $value);
        $cast = $value->arguments[0];
        self::assertInstanceOf(CastExpression::class, $cast);
        self::assertSame(CastMode::Implicit, $cast->mode);
        self::assertSame('NULL', $cast->operand->spelling());
        self::assertSame('integer', $cast->type->name);
        self::assertSame('NULL', $cast->structure()->toString());
    }

    #[TestWith([CastMode::Explicit, 'explicit', 'CAST(1 AS text)'])]
    #[TestWith([CastMode::Implicit, 'implicit', '1'])]
    public function testSpellingReturnsTheConversionMode(CastMode $mode, string $spelling, string $serialized): void
    {
        $operand = Expression::literal(1, Dialect::PostgreSql);
        $cast = new CastExpression(new ExpressionFacts(TypeDescriptor::builtin(Dialect::PostgreSql, 'text'), Nullability::NotNull), $operand->source, $operand, $mode);
        self::assertSame($spelling, $cast->spelling());
        self::assertSame($serialized, $cast->structure()->toString());
    }

    public function testRejectsAnOperandFromAnotherDialect(): void
    {
        $operand = Expression::literal(1, Dialect::Sqlite);
        $this->expectException(InvalidStructure::class);
        new CastExpression(new ExpressionFacts(TypeDescriptor::builtin(Dialect::PostgreSql, 'text'), Nullability::NotNull), $operand->source, $operand, CastMode::Explicit);
    }

    public function testWithFactsKeepsTheOperandAndMode(): void
    {
        $operand = Expression::literal(1, Dialect::PostgreSql);
        $cast = new CastExpression(new ExpressionFacts(TypeDescriptor::builtin(Dialect::PostgreSql, 'text'), Nullability::NotNull), $operand->source, $operand, CastMode::Explicit);
        $copy = $cast->withFacts(new ExpressionFacts(TypeDescriptor::builtin(Dialect::PostgreSql, 'varchar'), Nullability::MaybeNull));
        self::assertNotSame($cast, $copy);
        self::assertSame($operand, $copy->operand);
        self::assertSame(CastMode::Explicit, $copy->mode);
        self::assertSame('varchar', $copy->type->name);
        self::assertSame('text', $cast->type->name);
        self::assertSame(Nullability::NotNull, $cast->nullability);
    }
}
