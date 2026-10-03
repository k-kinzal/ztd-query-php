<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Literal\Radix;
use SqlSemantics\Statement\Literal\UnsignedInteger;

#[CoversClass(UnsignedInteger::class)]
#[Small]
final class UnsignedIntegerTest extends TestCase
{
    #[TestWith(['ff', Radix::Hexadecimal, '255'])]
    #[TestWith(['FF_FF', Radix::Hexadecimal, '65535'])]
    #[TestWith(['ffffffffffffffff', Radix::Hexadecimal, '18446744073709551615'])]
    #[TestWith(['10000000000000000', Radix::Hexadecimal, '18446744073709551616'])]
    #[TestWith(['ffffffffffffffffffffffffffffffff', Radix::Hexadecimal, '340282366920938463463374607431768211455'])]
    #[TestWith(['1111_1111', Radix::Binary, '255'])]
    #[TestWith(['377', Radix::Octal, '255'])]
    #[TestWith(['000_000', Radix::Decimal, '0'])]
    #[TestWith(['9_223_372_036_854_775_808', Radix::Decimal, '9223372036854775808'])]
    public function testDecimalPreservesEveryDigitAcrossMachineNumberBoundaries(string $digits, Radix $radix, string $decimal): void
    {
        $integer = new UnsignedInteger($digits, $radix);
        self::assertSame($decimal, $integer->decimal());
        self::assertSame($digits, $integer->digits);
    }

    public function testDecimalDoesNotNeedARegularExpressionStackForLongValues(): void
    {
        $digits = str_repeat('9', 100_000);
        self::assertSame($digits, (new UnsignedInteger($digits))->decimal());
    }

    #[TestWith(['0', '1'])]
    #[TestWith(['009', '10'])]
    #[TestWith(['999', '1000'])]
    #[TestWith(['18446744073709551615', '18446744073709551616'])]
    public function testSuccessorPreservesExactArithmeticAndTheOriginal(string $digits, string $next): void
    {
        $integer = new UnsignedInteger($digits);
        $successor = $integer->successor();
        self::assertSame($next, $successor->decimal());
        self::assertSame($digits, $integer->digits);
        self::assertNotSame($integer, $successor);
    }
}
