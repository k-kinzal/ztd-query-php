<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Expression\NumericClass;
use SqlSemantics\Platform\MySql\Rules\Expression\NumericResult;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\Binary;
use SqlSemantics\Platform\MySql\Statement\Type\Decimal;
use SqlSemantics\Platform\MySql\Statement\Type\Floating;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\FloatingKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\NumericModifier;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Type\Choice;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(NumericResult::class)]
#[Small]
final class NumericResultTest extends TestCase
{
    public function testBinaryTypesASumOfAnIntegerAndAString(): void
    {
        $integer = new ScalarFact(new Known(new Integral(IntegralKind::Int)), Nullability::NotNull);
        $text = new ScalarFact(new Known(new Binary(BinaryKind::VarBinary)), Nullability::NotNull);

        self::assertEquals(new Known(new Floating(FloatingKind::Double)), (new NumericResult())->binary(ArithmeticOperator::Plus, new NumberLiteral('1'), $integer, new StringLiteral(['2']), $text, GrammarRelease::MySql847));
    }

    public function testBinaryMakesAnUnsignedSubtractionTheChoiceTheSessionModeDecides(): void
    {
        $unsigned = new ScalarFact(new Known(new Integral(IntegralKind::Int, null, [NumericModifier::Unsigned])), Nullability::NotNull);

        self::assertEquals(new Choice([new Integral(IntegralKind::BigInt, null, [NumericModifier::Unsigned]), new Integral(IntegralKind::BigInt)]), (new NumericResult())->binary(ArithmeticOperator::Minus, new NumberLiteral('1'), $unsigned, new NumberLiteral('2'), $unsigned, GrammarRelease::MySql847));
    }

    public function testBinaryMakesABitOperationOfBinaryStringsABinaryStringFromMySql80(): void
    {
        $bytes = new ScalarFact(new Known(new Binary(BinaryKind::VarBinary)), Nullability::NotNull);
        $results = new NumericResult();

        self::assertEquals(new Known(new Binary(BinaryKind::VarBinary)), $results->binary(ArithmeticOperator::BitAnd, new StringLiteral(['a']), $bytes, new StringLiteral(['b']), $bytes, GrammarRelease::MySql847));
        self::assertEquals(new Known(new Integral(IntegralKind::BigInt, null, [NumericModifier::Unsigned])), $results->binary(ArithmeticOperator::BitAnd, new StringLiteral(['a']), $bytes, new StringLiteral(['b']), $bytes, GrammarRelease::MySql5744));
    }

    public function testArithmeticFollowsTheOperandClasses(): void
    {
        $results = new NumericResult();

        self::assertEquals(
            [new Decimal(), new Integral(IntegralKind::BigInt, null, [NumericModifier::Unsigned]), new Integral(IntegralKind::BigInt), new Floating(FloatingKind::Double), null],
            [
                $results->arithmetic(ArithmeticOperator::Divide, NumericClass::Signed, NumericClass::Signed),
                $results->arithmetic(ArithmeticOperator::Modulo, NumericClass::Unsigned, NumericClass::Signed),
                $results->arithmetic(ArithmeticOperator::Modulo, NumericClass::Signed, NumericClass::Unsigned),
                $results->arithmetic(ArithmeticOperator::Multiply, NumericClass::Decimal, NumericClass::Double),
                $results->arithmetic(ArithmeticOperator::Minus, NumericClass::Signed, NumericClass::Unsigned),
            ],
        );
    }

    public function testBitsIgnoreAHexadecimalLiteral(): void
    {
        $bytes = new ScalarFact(new Known(new Binary(BinaryKind::VarBinary)), Nullability::NotNull);

        self::assertEquals(new Known(new Integral(IntegralKind::BigInt, null, [NumericModifier::Unsigned])), (new NumericResult())->bits([[new \SqlSemantics\Platform\MySql\Statement\Literal\RadixLiteral(\SqlSemantics\Platform\MySql\Statement\Literal\Radix::Hexadecimal, '01'), $bytes]], GrammarRelease::MySql847));
    }

    public function testNegationMakesALiteralBeyondTheRangeADecimal(): void
    {
        $fact = new ScalarFact(new Known(new Integral(IntegralKind::BigInt, null, [NumericModifier::Unsigned])), Nullability::NotNull);
        $results = new NumericResult();

        self::assertEquals(new Known(new Decimal()), $results->negation(new NumberLiteral('9223372036854775809'), $fact));
        self::assertEquals(new Known(new Integral(IntegralKind::BigInt)), $results->negation(new NumberLiteral('9223372036854775808'), $fact));
    }

    public function testBeyondComparesWithTheNegatedRange(): void
    {
        self::assertSame([false, true, false], [(new NumericResult())->beyond(new NumberLiteral('9223372036854775808')), (new NumericResult())->beyond(new NumberLiteral('18446744073709551615')), (new NumericResult())->beyond(new StringLiteral(['1']))]);
    }

    public function testIntegerIsABigint(): void
    {
        self::assertTrue((new NumericResult())->integer(true)->unsigned());
        self::assertSame('BIGINT', (new NumericResult())->integer(false)->name());
    }
}
