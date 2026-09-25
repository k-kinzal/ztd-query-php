<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Operator\Qualified;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Operator\Qualified\QualifiedOperator;
use SqlSemantics\Model\Scalar\Operator\Qualified\QualifiedPrefixOperation;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(QualifiedPrefixOperation::class)]
#[Medium]
final class QualifiedPrefixOperationTest extends TestCase
{
    public function testInputsListTheOperand(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (n INTEGER)'));
        $query = $binder->bind('SELECT OPERATOR(public.@@) n FROM t');
        self::assertInstanceOf(BoundSelect::class, $query);
        $operation = $query->outputs[0]->expression;
        self::assertInstanceOf(QualifiedPrefixOperation::class, $operation);
        self::assertSame([$operation->operand], $operation->inputs());
        self::assertSame(ExpressionKind::Operator, $operation->kind);
        self::assertSame('unknown', $operation->type->name);
        self::assertSame(Nullability::Unknown, $operation->nullability);
        self::assertSame('SELECT (OPERATOR("public".@@) "n") FROM "public"."t"', (new \SqlSemantics\SimpleSerializer())->serialize($query));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($query), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($query))));
    }

    public function testSpellingNamesTheOperator(): void
    {
        $value = Expression::literal(1, Dialect::PostgreSql);
        $operation = new QualifiedPrefixOperation($value->facts, $value->source, new QualifiedOperator(['geo'], '@@'), $value);
        self::assertSame('OPERATOR(geo.@@)', $operation->spelling());
    }

    public function testWithFactsKeepsTheOperatorAndOperand(): void
    {
        $value = Expression::literal(1, Dialect::PostgreSql);
        $operation = new QualifiedPrefixOperation($value->facts, $value->source, new QualifiedOperator([], '#'), $value);
        $facts = new ExpressionFacts(TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'), Nullability::MaybeNull, ['j1']);
        $copy = $operation->withFacts($facts);
        self::assertNotSame($operation, $copy);
        self::assertSame($operation->operator, $copy->operator);
        self::assertSame($facts, $copy->facts);
        self::assertSame('integer', $operation->type->name);
    }

    public function testInputsRequirePostgreSqlOperands(): void
    {
        $value = Expression::literal(1, Dialect::MySql);
        $this->expectException(InvalidStructure::class);
        new QualifiedPrefixOperation($value->facts, $value->source, new QualifiedOperator([], '#'), $value);
    }
}
