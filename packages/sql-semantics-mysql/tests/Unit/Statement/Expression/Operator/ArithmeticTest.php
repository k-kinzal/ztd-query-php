<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Interval;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\IntervalAddition;
use SqlSemantics\Platform\MySql\Statement\Expression\Truth;
use SqlSemantics\Platform\MySql\Statement\Expression\TruthTest;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\Decimal;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(Arithmetic::class)]
#[Medium]
final class ArithmeticTest extends TestCase
{
    public function testDeriveScalarTypesTheResultAndMakesADivisionNullable(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));
        $sum = $derivation->scalar(new Arithmetic(ArithmeticOperator::Plus, new NumberLiteral('1'), new NumberLiteral('2.5')), $derivation->environment());
        $quotient = $derivation->scalar(new Arithmetic(ArithmeticOperator::IntegerDivide, new NumberLiteral('1'), new NumberLiteral('2')), $derivation->environment());

        self::assertEquals([new Known(new Decimal()), Nullability::NotNull], [$sum->type, $sum->nullability]);
        self::assertEquals([new Known(new Integral(IntegralKind::BigInt)), Nullability::Nullable], [$quotient->type, $quotient->nullability]);
    }

    public function testRenderWritesDivAsAKeywordAndKeepsTheLeftAssociation(): void
    {
        $platform = new Platform();
        $out = new Output($platform->codec($platform->profile('mysql-8.4.7', null, ParameterStyle::Native)));
        (new Arithmetic(ArithmeticOperator::Minus, new Arithmetic(ArithmeticOperator::Minus, new NumberLiteral('1'), new NumberLiteral('2')), new Arithmetic(ArithmeticOperator::IntegerDivide, new NumberLiteral('3'), new NumberLiteral('4'))))->render($out);

        self::assertSame('1 - 2 - 3 DIV 4', (new Lexical())->join($out->pieces()));
    }

    public function testARightOperandOfTheSameLevelIsRejected(): void
    {
        $this->expectExceptionMessage('The right operand of - needs a grouping to keep its place.');

        new Arithmetic(ArithmeticOperator::Minus, new NumberLiteral('1'), new Arithmetic(ArithmeticOperator::Plus, new NumberLiteral('2'), new NumberLiteral('3')));
    }

    public function testAWeakerLeftOperandIsRejected(): void
    {
        $this->expectExceptionMessage('The left operand of * needs a grouping to keep its place.');

        new Arithmetic(ArithmeticOperator::Multiply, new Arithmetic(ArithmeticOperator::Plus, new NumberLiteral('1'), new NumberLiteral('2')), new NumberLiteral('3'));
    }

    public function testARightOperandStartingWithALeadingIntervalIsRejected(): void
    {
        $this->expectExceptionMessage('A leading interval after + is read as a trailing interval and needs a grouping.');

        new Arithmetic(ArithmeticOperator::Plus, new NumberLiteral('1'), new Arithmetic(ArithmeticOperator::Multiply, new IntervalAddition(new Interval(new NumberLiteral('2'), IntervalUnit::Day), new TruthTest(new NumberLiteral('3'), Truth::True)), new NumberLiteral('4')));
    }
}
