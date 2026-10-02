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

    public function testPartsKeepsFractionDigitsAndCanonicalizesTheRest(): void
    {
        self::assertSame(['10', '50', '-3'], (new Numerals())->parts('1_0.50e-3'));
        self::assertSame(['0', '5', null], (new Numerals())->parts('.5'));
        self::assertSame(['1', '', null], (new Numerals())->parts('1.'));
        self::assertSame(['1', '', '10'], (new Numerals())->parts('1E+010'));
        self::assertSame(['1', '0', null], (new Numerals())->parts('1.0e0'));
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
