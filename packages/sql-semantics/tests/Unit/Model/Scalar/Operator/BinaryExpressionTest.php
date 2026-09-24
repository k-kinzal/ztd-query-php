<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Model\Scalar\Operator\BinaryExpression::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class BinaryExpressionTest extends TestCase
{
    public function testRejectsOperandsFromDifferentDialects(): void
    {
        $left = Expression::literal(1, Dialect::PostgreSql);
        $right = Expression::literal(2, Dialect::Sqlite);
        $this->expectException(InvalidStructure::class);
        new \SqlSemantics\Model\Scalar\Operator\BinaryExpression($left->facts, $left->source, \SqlSemantics\Model\Scalar\Operator\BinaryOperator::Add, $left, $right);
    }

    public function testCaseOperandRemainsInsideItsBinaryOperationAfterSerialization(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build());
        $query = $binder->bind('SELECT (CASE WHEN 1 THEN 2 ELSE 3 END) + 4');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        $rebound = $binder->bind($query->toString());
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $rebound);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Operator\BinaryExpression::class, $rebound->outputs[0]->expression);
        self::assertSame('case', $rebound->outputs[0]->expression->left->kind->value);
        self::assertSame('4', $rebound->outputs[0]->expression->right->spelling());
    }

    public function testInputsOrdersTheLeftOperandFirst(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $query = $binder->bind('SELECT 1 + 2');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        $operation = $query->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Operator\BinaryExpression::class, $operation);
        self::assertSame('1', $operation->left->spelling());
        self::assertSame('2', $operation->right->spelling());
        self::assertSame([$operation->left, $operation->right], $operation->inputs());
        self::assertSame('SELECT (1 + 2)', $query->toString());
        self::assertSame('SELECT (1 + 2)', $binder->bind($query->toString())->toString());
    }

    public function testSpellingReturnsTheOperatorSymbol(): void
    {
        $left = Expression::literal('a', Dialect::PostgreSql);
        $right = Expression::literal('b', Dialect::PostgreSql);
        $operation = new \SqlSemantics\Model\Scalar\Operator\BinaryExpression($left->facts, $left->source, \SqlSemantics\Model\Scalar\Operator\BinaryOperator::Concat, $left, $right);
        self::assertSame('||', $operation->spelling());
        self::assertSame("('a' || 'b')", $operation->structure()->toString());
    }

    public function testWithFactsKeepsTheOperatorAndOperands(): void
    {
        $left = Expression::literal(1, Dialect::PostgreSql);
        $right = Expression::literal(2, Dialect::PostgreSql);
        $operation = new \SqlSemantics\Model\Scalar\Operator\BinaryExpression($left->facts, $left->source, \SqlSemantics\Model\Scalar\Operator\BinaryOperator::Add, $left, $right);
        $copy = $operation->withFacts(new \SqlSemantics\Model\Scalar\ExpressionFacts($operation->type, \SqlSemantics\Type\Nullability::MaybeNull));
        self::assertNotSame($operation, $copy);
        self::assertSame(\SqlSemantics\Model\Scalar\Operator\BinaryOperator::Add, $copy->operator);
        self::assertSame([$left, $right], $copy->inputs());
        self::assertSame(\SqlSemantics\Type\Nullability::MaybeNull, $copy->nullability);
        self::assertSame(\SqlSemantics\Type\Nullability::NotNull, $operation->nullability);
    }
}
