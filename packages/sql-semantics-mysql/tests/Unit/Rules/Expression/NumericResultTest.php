<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Expression\NumericClass;
use SqlSemantics\Platform\MySql\Rules\Expression\NumericResult;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator;
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
use SqlSemantics\Statement\Fact\Warning;
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

    public function testBitsAreBinaryWhenOneBinaryStringMeetsLiterals(): void
    {
        $bytes = new ScalarFact(new Known(new Binary(BinaryKind::VarBinary)), Nullability::NotNull);
        $integer = new ScalarFact(new Known(new Integral(IntegralKind::BigInt)), Nullability::NotNull);
        $introduced = new \SqlSemantics\Platform\MySql\Statement\Literal\RadixLiteral(\SqlSemantics\Platform\MySql\Statement\Literal\Radix::Hexadecimal, '40', new \SqlSemantics\Statement\Identifier\Name('binary'));
        $hexadecimal = new \SqlSemantics\Platform\MySql\Statement\Literal\RadixLiteral(\SqlSemantics\Platform\MySql\Statement\Literal\Radix::Hexadecimal, '01');

        self::assertEquals(
            [new Known(new Binary(BinaryKind::VarBinary)), new Known(new Binary(BinaryKind::VarBinary)), new Known(new Integral(IntegralKind::BigInt, null, [NumericModifier::Unsigned]))],
            [
                (new NumericResult())->bits([[$introduced, $bytes], [$hexadecimal, $bytes]], GrammarRelease::MySql847),
                (new NumericResult())->bits([[new StringLiteral(['a']), $bytes], [new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral(), $bytes]], GrammarRelease::MySql847),
                (new NumericResult())->bits([[$introduced, $bytes], [new NumberLiteral('1'), $integer]], GrammarRelease::MySql847),
            ],
        );
    }

    public function testNegationMakesALiteralBeyondTheRangeADecimal(): void
    {
        $fact = new ScalarFact(new Known(new Integral(IntegralKind::BigInt, null, [NumericModifier::Unsigned])), Nullability::NotNull);
        $results = new NumericResult();

        self::assertEquals(new Known(new Decimal()), $results->negation(new NumberLiteral('9223372036854775809'), $fact));
        self::assertEquals(new Known(new Integral(IntegralKind::BigInt)), $results->negation(new NumberLiteral('9223372036854775808'), $fact));
    }

    public function testNegationMakesANegativeIntegerConstantADecimal(): void
    {
        $fact = new ScalarFact(new Known(new Integral(IntegralKind::BigInt)), Nullability::NotNull);

        self::assertEquals(new Known(new Decimal()), (new NumericResult())->negation(new Unary(UnaryOperator::Minus, new NumberLiteral('3')), $fact));
        self::assertEquals(new Known(new Integral(IntegralKind::BigInt)), (new NumericResult())->negation(new NumberLiteral('3'), $fact));
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

    public function testBinaryOperandWarnsOfABinaryStringLeftOperandIn57(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.7.44');

        self::assertSame(["Bitwise operations on BINARY will change behavior in a future version, check the 'Bit functions' section in the manual."], array_map(static fn (Warning $warning): string => $warning->message(), $semantics->analyze("SELECT _binary 'a' << 1")->facts->warnings));
        self::assertSame([], $semantics->analyze("SELECT 1 << _binary 'a', X'0f' | 1, ~b'1'")->facts->warnings);
        self::assertSame([], (new Semantics(Dialect::MySql))->analyze("SELECT _binary 'a' | 1")->facts->warnings);
    }

    public function testBinaryOperationWarnsOfBinaryStringsAmongLiteralsIn57(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.7.44');

        self::assertCount(1, $semantics->analyze("SELECT NULL | BINARY 'a'")->facts->warnings);
        self::assertCount(1, $semantics->analyze("SELECT X'01' ^ _binary 'a'")->facts->warnings);
        self::assertSame([], $semantics->analyze("SELECT BINARY 'a' | 1, CAST('a' AS BINARY) & 2.5, X'01' | NULL")->facts->warnings);
    }
}
