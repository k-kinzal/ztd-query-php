<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Conditional;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Conditional\NullIf;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(NullIf::class)]
#[Medium]
final class NullIfTest extends TestCase
{
    public function testInputsOrdersTheComparedOperands(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('SELECT NULLIF(1, 2)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $value = $statement->outputs[0]->expression;
        self::assertInstanceOf(NullIf::class, $value);
        self::assertSame('1', $value->left->spelling());
        self::assertSame('2', $value->right->spelling());
        self::assertSame([$value->left, $value->right], $value->inputs());
        self::assertSame('integer', $value->type->name);
        self::assertSame(Nullability::MaybeNull, $value->nullability);
        self::assertSame('SELECT NULLIF(1, 2)', $statement->toString());
        self::assertSame('SELECT NULLIF(1, 2)', $binder->bind($statement->toString())->toString());
    }

    public function testSpellingIsTheFunctionKeyword(): void
    {
        $left = Expression::literal(1, Dialect::PostgreSql);
        $value = new NullIf($left->facts, $left->source, $left, Expression::literal(2, Dialect::PostgreSql));
        self::assertSame('NULLIF', $value->spelling());
        self::assertSame('NULLIF(1, 2)', $value->structure()->toString());
    }

    public function testRejectsOperandsFromAnotherDialect(): void
    {
        $left = Expression::literal(1, Dialect::PostgreSql);
        $this->expectException(InvalidStructure::class);
        new NullIf($left->facts, $left->source, $left, Expression::literal(2, Dialect::MySql));
    }

    public function testWithFactsKeepsBothOperands(): void
    {
        $left = Expression::literal(1, Dialect::PostgreSql);
        $right = Expression::literal(2, Dialect::PostgreSql);
        $value = new NullIf($left->facts, $left->source, $left, $right);
        $copy = $value->withFacts(new ExpressionFacts($value->type, Nullability::MaybeNull));
        self::assertNotSame($value, $copy);
        self::assertSame($left, $copy->left);
        self::assertSame($right, $copy->right);
        self::assertSame(Nullability::MaybeNull, $copy->nullability);
        self::assertSame(Nullability::NotNull, $value->nullability);
    }
}
