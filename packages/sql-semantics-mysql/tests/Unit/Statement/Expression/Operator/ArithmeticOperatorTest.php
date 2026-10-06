<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;

#[CoversClass(ArithmeticOperator::class)]
#[Small]
final class ArithmeticOperatorTest extends TestCase
{
    public function testLevelFollowsTheOperatorPrecedenceOfTheManual(): void
    {
        self::assertSame(
            [Precedence::BIT_EXPR, Precedence::BIT_AND, Precedence::SHIFT, Precedence::ADDITIVE, Precedence::MULTIPLICATIVE, Precedence::MULTIPLICATIVE, Precedence::BIT_XOR],
            [ArithmeticOperator::BitOr->level(), ArithmeticOperator::BitAnd->level(), ArithmeticOperator::ShiftLeft->level(), ArithmeticOperator::Minus->level(), ArithmeticOperator::IntegerDivide->level(), ArithmeticOperator::Modulo->level(), ArithmeticOperator::BitXor->level()],
        );
    }

    public function testBitwiseSeparatesTheBitOperators(): void
    {
        self::assertSame([true, true, false, false], [ArithmeticOperator::ShiftRight->bitwise(), ArithmeticOperator::BitXor->bitwise(), ArithmeticOperator::Plus->bitwise(), ArithmeticOperator::Divide->bitwise()]);
    }
}
