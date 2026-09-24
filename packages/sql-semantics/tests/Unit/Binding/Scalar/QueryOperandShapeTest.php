<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Scalar\QueryOperandShape::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class QueryOperandShapeTest extends TestCase
{
    #[TestWith(['SELECT 1 IN (SELECT 1, 2)'])]
    #[TestWith(['SELECT ROW(1, 2) IN (SELECT 1)'])]
    #[TestWith(['SELECT 1 = ANY (SELECT 1, 2)'])]
    public function testCheckReportsStructuralWidthMismatch(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidSql::class);
        $binder->bind($sql, strict: false);
    }

    public function testWidthCountsScalarsRowsAndQueryColumns(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('SELECT 1, ROW(1, 2), (SELECT 1), (a, a) = (SELECT 1, 2), a IN (SELECT a FROM t) FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $widths = array_map(static fn ($output): ?int => \SqlSemantics\Binding\Scalar\QueryOperandShape::width($output->expression), $statement->outputs);
        self::assertSame([1, 2, 1, 1, 1], $widths);
        $comparison = $statement->outputs[3]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Operator\BinaryExpression::class, $comparison);
        self::assertSame(2, \SqlSemantics\Binding\Scalar\QueryOperandShape::width($comparison->right));
    }
}
