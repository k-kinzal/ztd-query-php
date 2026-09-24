<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Query\AlternativeFacts;
use SqlSemantics\Model\Scalar\Operator\CastExpression;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\CompoundStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(AlternativeFacts::class)]
#[Medium]
final class AlternativeFactsTest extends TestCase
{
    public function testOfKeepsTheCommonTypeWhenEveryAlternativeIsNotNull(): void
    {
        $facts = AlternativeFacts::of(Dialect::PostgreSql, [Expression::literal(1, Dialect::PostgreSql), Expression::literal(2, Dialect::PostgreSql)]);
        self::assertSame('integer', $facts->type->name);
        self::assertSame(Nullability::NotNull, $facts->nullability);
    }

    public function testOfWidensNullabilityWhenOneAlternativeIsNull(): void
    {
        $facts = AlternativeFacts::of(Dialect::PostgreSql, [Expression::literal(1, Dialect::PostgreSql), Expression::literal(null, Dialect::PostgreSql)]);
        self::assertSame('integer', $facts->type->name);
        self::assertSame(Nullability::MaybeNull, $facts->nullability);
    }

    public function testOfLeavesTheTypeUnknownWhenAnOperandIsUnresolved(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)'));
        $statement = $binder->bind('SELECT missing FROM t', strict: false);
        self::assertInstanceOf(BoundSelect::class, $statement);
        $facts = AlternativeFacts::of(Dialect::PostgreSql, [Expression::literal(1, Dialect::PostgreSql), $statement->outputs[0]->expression]);
        self::assertSame('unknown', $facts->type->name);
        self::assertSame(Nullability::Unknown, $facts->nullability);
    }

    public function testSetInputUnwrapsAnImplicitCastOfAnUntypedLiteral(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SELECT 1 UNION SELECT '2'");
        self::assertInstanceOf(CompoundStatement::class, $statement);
        self::assertInstanceOf(BoundSelect::class, $statement->right);
        $cast = $statement->right->outputs[0]->expression;
        self::assertInstanceOf(CastExpression::class, $cast);
        $input = AlternativeFacts::setInput($cast);
        self::assertInstanceOf(Literal::class, $input);
        self::assertSame("'2'", $input->text);
        self::assertSame('unknown', $input->type->name);
        self::assertSame('integer', $statement->outputs[0]->expression->type->name);
    }

    public function testSetInputReturnsATypedOperandUnchanged(): void
    {
        $value = Expression::literal(1, Dialect::PostgreSql);
        self::assertSame($value, AlternativeFacts::setInput($value));
    }
}
