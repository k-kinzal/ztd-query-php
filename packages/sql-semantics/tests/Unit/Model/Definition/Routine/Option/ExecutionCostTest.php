<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Option\ExecutionCost;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(ExecutionCost::class)]
#[Medium]
final class ExecutionCostTest extends TestCase
{
    public function testRetainsAPositiveCost(): void
    {
        $cost = Expression::literal(0.5, Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $cost);
        self::assertSame($cost, (new ExecutionCost($cost))->cost);
    }

    public function testRejectsAZeroCost(): void
    {
        $cost = Expression::literal(0, Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $cost);
        $this->expectException(InvalidStructure::class);
        new ExecutionCost($cost);
    }
}
