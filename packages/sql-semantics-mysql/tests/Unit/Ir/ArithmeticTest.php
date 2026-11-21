<?php

declare(strict_types=1);

namespace Tests\Unit\Ir;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Arithmetic;
use SqlSemantics\Platform\MySql\Ir\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;

#[CoversClass(Arithmetic::class)]
#[Small]
final class ArithmeticTest extends TestCase
{
    public function testPreservesOperandRoles(): void
    {
        $left = new IntegerLiteral('2');
        $right = new IntegerLiteral('7');
        $result = new Arithmetic(ArithmeticOperator::Subtract, $left, $right);

        self::assertSame($left, $result->left);
        self::assertSame($right, $result->right);
    }
}
