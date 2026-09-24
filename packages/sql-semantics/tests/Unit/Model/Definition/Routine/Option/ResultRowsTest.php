<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Option\ResultRows;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(ResultRows::class)]
#[Medium]
final class ResultRowsTest extends TestCase
{
    public function testRetainsAPositiveEstimate(): void
    {
        $rows = Expression::literal(100, Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $rows);
        self::assertSame($rows, (new ResultRows($rows))->rows);
    }

    public function testRejectsAZeroEstimate(): void
    {
        $rows = Expression::literal(0, Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $rows);
        $this->expectException(InvalidStructure::class);
        new ResultRows($rows);
    }
}
