<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\SpatialArithmetic;

#[CoversClass(SpatialArithmetic::class)]
#[Small]
final class SpatialArithmeticTest extends TestCase
{
    public function testMessageRetainsTheOperatorWithoutSqlOrValues(): void
    {
        self::assertSame('Incorrect arguments to DIV.', (new SpatialArithmetic(ArithmeticOperator::IntegerDivide))->message());
        self::assertSame('Incorrect arguments to ~.', (new SpatialArithmetic(UnaryOperator::Invert))->message());
    }

    public function testLogicalNegationCannotDescribeARefusedSpatialOperand(): void
    {
        $this->expectExceptionMessage('Only numeric negation and bit inversion refuse unary geometry operands.');
        new SpatialArithmetic(UnaryOperator::Not);
    }
}
