<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberForm;

#[CoversClass(NumberForm::class)]
#[Small]
final class NumberFormTest extends TestCase
{
    public function testCasesNameTheThreeForms(): void
    {
        self::assertSame(['Integer', 'Decimal', 'Float'], array_column(NumberForm::cases(), 'name'));
    }

    public function testOfClassifiesDigitsAsAnIntegerUpToTheUnsignedLimit(): void
    {
        self::assertSame(NumberForm::Integer, NumberForm::of('0'));
        self::assertSame(NumberForm::Integer, NumberForm::of('007'));
        self::assertSame(NumberForm::Integer, NumberForm::of('18446744073709551615'));
        self::assertSame(NumberForm::Integer, NumberForm::of('0000018446744073709551615'));
    }

    public function testOfClassifiesDigitsBeyondTheUnsignedLimitAsADecimal(): void
    {
        self::assertSame(NumberForm::Decimal, NumberForm::of('18446744073709551616'));
        self::assertSame(NumberForm::Decimal, NumberForm::of('99999999999999999999'));
        self::assertSame(NumberForm::Decimal, NumberForm::of('100000000000000000000'));
    }

    public function testOfClassifiesADecimalPointAsADecimal(): void
    {
        self::assertSame(NumberForm::Decimal, NumberForm::of('1.5'));
        self::assertSame(NumberForm::Decimal, NumberForm::of('1.'));
        self::assertSame(NumberForm::Decimal, NumberForm::of('.5'));
    }

    public function testOfClassifiesAnExponentAsAFloat(): void
    {
        self::assertSame(NumberForm::Float, NumberForm::of('1e3'));
        self::assertSame(NumberForm::Float, NumberForm::of('1.e3'));
        self::assertSame(NumberForm::Float, NumberForm::of('.5E+2'));
        self::assertSame(NumberForm::Float, NumberForm::of('1E-3'));
    }

    public function testOfAnswersNullForTextThatIsNoUnsignedNumber(): void
    {
        self::assertNull(NumberForm::of(''));
        self::assertNull(NumberForm::of('e3'));
        self::assertNull(NumberForm::of('1e'));
        self::assertNull(NumberForm::of('-1'));
        self::assertNull(NumberForm::of(' 1'));
        self::assertNull(NumberForm::of('1 '));
        self::assertNull(NumberForm::of('0x1F'));
    }
}
