<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scalar\RowBinder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RowBinder::class)]
#[Medium]
final class RowBinderTest extends TestCase
{
    public function testBindReadsExplicitAndImplicitRowConstructors(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('SELECT ROW(1, 2), (1, 2) = (3, 4) FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $explicit = $statement->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\RowExpression::class, $explicit);
        self::assertCount(2, $explicit->items);
        self::assertSame('record', $explicit->type->name);
        self::assertSame(\SqlSemantics\Type\Nullability::NotNull, $explicit->nullability);
        $comparison = $statement->outputs[1]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Operator\BinaryExpression::class, $comparison);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\RowExpression::class, $comparison->left);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\RowExpression::class, $comparison->right);
        self::assertSame('SELECT ROW(1, 2), (ROW(1, 2) = ROW(3, 4)) FROM "public"."t"', $statement->toString());
    }

    public function testBindKeepsColumnOperandsInsideTheRow(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT, b INT)')))->bind('SELECT ROW(a, b) = ROW(3, 4) FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $comparison = $statement->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Operator\BinaryExpression::class, $comparison);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\RowExpression::class, $comparison->left);
        self::assertSame(['a', 'b'], array_map(static fn ($item): ?string => $item->columnBinding()?->column->name, $comparison->left->items));
        self::assertSame('SELECT ((`a`, `b`) = (3, 4)) FROM `t`', $statement->toString());
    }

    public function testBindLeavesScalarParenthesesAlone(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('SELECT (a) FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\ColumnReference::class, $statement->outputs[0]->expression);
    }
}
