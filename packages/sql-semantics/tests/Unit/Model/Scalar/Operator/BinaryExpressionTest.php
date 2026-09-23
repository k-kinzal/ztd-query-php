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
}
