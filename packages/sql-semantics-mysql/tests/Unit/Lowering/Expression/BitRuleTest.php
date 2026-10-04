<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Expression\BitRule;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\IntervalArithmetic;

#[CoversClass(BitRule::class)]
#[Medium]
final class BitRuleTest extends TestCase
{
    public function testBitExpressionBuildsTheLeftAssociationAndTheIntervalForm(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $expression = (new BitRule($lowering))->bitExpression($platform->parser($profile)->parse('SELECT 1 MOD 2 * 3 - INTERVAL 4 DAY')->find('bit_expr')[0]);

        self::assertInstanceOf(IntervalArithmetic::class, $expression);
        self::assertTrue($expression->subtract);
        self::assertInstanceOf(Arithmetic::class, $expression->operand);
        self::assertSame(ArithmeticOperator::Multiply, $expression->operand->operator);
        self::assertInstanceOf(Arithmetic::class, $expression->operand->left);
        self::assertSame(ArithmeticOperator::Modulo, $expression->operand->left->operator);
    }
}
