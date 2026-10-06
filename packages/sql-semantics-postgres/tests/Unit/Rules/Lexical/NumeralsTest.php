<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Lexical;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Rules\Lexical\Numerals;

#[CoversClass(Numerals::class)]
#[Small]
final class NumeralsTest extends TestCase
{
    public function testIntegralTellsIntegerSpellingsFromNumericOnes(): void
    {
        self::assertTrue((new Numerals())->integral('1_000'));
        self::assertTrue((new Numerals())->integral('0xFF'));
        self::assertFalse((new Numerals())->integral('1.'));
        self::assertFalse((new Numerals())->integral('1e5'));
    }

    public function testDecimalConvertsEveryBaseExactly(): void
    {
        self::assertSame('31', (new Numerals())->decimal('0x1_F'));
        self::assertSame('15', (new Numerals())->decimal('0o17'));
        self::assertSame('5', (new Numerals())->decimal('0b101'));
        self::assertSame('7', (new Numerals())->decimal('007'));
        self::assertSame('1208925819614629174706175', (new Numerals())->decimal('0xFFFFFFFFFFFFFFFFFFFF'));
    }

    public function testIntegerReadsOnlyIntegerSpellings(): void
    {
        self::assertSame('8589934591', (new Numerals())->integer('0x1_FFFF_FFFF'));
        self::assertNull((new Numerals())->integer('1e2'));
    }

    public function testKeptTellsTheTextsTheScannerKeepsAsFconst(): void
    {
        self::assertSame(
            [true, true, true, true, true, true, true],
            [(new Numerals())->kept('1_0.50e-3'), (new Numerals())->kept('.5'), (new Numerals())->kept('1.'), (new Numerals())->kept('1E+010'), (new Numerals())->kept('2147483648'), (new Numerals())->kept('0x1FFFFFFFFF'), (new Numerals())->kept('0b1_0000_0000_0000_0000_0000_0000_0000_0000')],
        );
        self::assertSame(
            [false, false, false, false, false, false],
            [(new Numerals())->kept('2147483647'), (new Numerals())->kept('0x7FFFFFFF'), (new Numerals())->kept('1e'), (new Numerals())->kept('1__0.5'), (new Numerals())->kept('-1.5'), (new Numerals())->kept('.')],
        );
    }

    public function testCanonicalStripsLeadingZeros(): void
    {
        self::assertSame('0', (new Numerals())->canonical('000'));
        self::assertSame('10', (new Numerals())->canonical('0010'));
    }

    public function testWithinComparesCanonicalDigits(): void
    {
        self::assertTrue((new Numerals())->within('2147483647', '2147483647'));
        self::assertFalse((new Numerals())->within('2147483648', '2147483647'));
        self::assertTrue((new Numerals())->within('99', '2147483647'));
    }
}
