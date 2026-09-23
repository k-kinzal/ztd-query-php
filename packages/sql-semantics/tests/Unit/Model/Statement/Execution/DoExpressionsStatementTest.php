<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Execution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Execution\DoExpressionsStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DoExpressionsStatement::class)]
#[Medium]
final class DoExpressionsStatementTest extends TestCase
{
    public function testWithExpressionsReturnsANewRequestWithoutEvaluatingOperands(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('DO 1 + 2');
        $other = $binder->bind('DO 4,5');
        self::assertInstanceOf(DoExpressionsStatement::class, $statement);
        self::assertInstanceOf(DoExpressionsStatement::class, $other);
        $changed = $statement->withExpressions($other->expressions);
        self::assertSame('DO 4, 5', $changed->toString());
        self::assertCount(1, $statement->expressions);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Operator\BinaryExpression::class, $statement->expressions[0]);
    }

    public function testWithOriginRetainsTheOrderedExpressions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DO ?, ? + 1');
        self::assertInstanceOf(DoExpressionsStatement::class, $statement);
        $changed = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $changed);
        self::assertSame($statement->expressions, $changed->expressions);
        self::assertSame('DO ?, (? + 1)', $changed->toString());
    }

    public function testWithExpressionsRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DO 1');
        self::assertInstanceOf(DoExpressionsStatement::class, $statement);
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        $statement->withExpressions([$query->outputs[0]->expression]);
    }

    public function testWithExpressionsCannotAcceptATableExpansion(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('DO 1');
        $query = $binder->bind('SELECT *', strict: false);
        self::assertInstanceOf(DoExpressionsStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        $statement->withExpressions([$query->outputs[0]->expression]);
    }

}
