<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\RadixSpelling;

#[CoversClass(RadixSpelling::class)]
#[Small]
final class RadixSpellingTest extends TestCase
{
    public function testDigitsReadsBothSpellingsOfAHexadecimalLiteral(): void
    {
        self::assertSame('1F', (new RadixSpelling())->digits("X'1F'"));
        self::assertSame('1f', (new RadixSpelling())->digits("x'1f'"));
        self::assertSame('1F', (new RadixSpelling())->digits('0x1F'));
        self::assertSame('01f', (new RadixSpelling())->digits('0x01f'));
        self::assertSame('', (new RadixSpelling())->digits("X''"));
    }

    public function testDigitsReadsBothSpellingsOfABitLiteral(): void
    {
        self::assertSame('101', (new RadixSpelling())->digits("b'101'"));
        self::assertSame('0101', (new RadixSpelling())->digits("B'0101'"));
        self::assertSame('101', (new RadixSpelling())->digits('0b101'));
        self::assertSame('', (new RadixSpelling())->digits("b''"));
    }

    public function testHexadecimalQuotesAnEvenCountAndPrefixesAnOddCount(): void
    {
        self::assertSame("x'1F'", (new RadixSpelling())->hexadecimal('1F'));
        self::assertSame("x''", (new RadixSpelling())->hexadecimal(''));
        self::assertSame("x'00ab'", (new RadixSpelling())->hexadecimal('00ab'));
        self::assertSame('0x1', (new RadixSpelling())->hexadecimal('1'));
        self::assertSame('0xabc', (new RadixSpelling())->hexadecimal('abc'));
    }

    public function testHexadecimalRoundTripsThroughDigits(): void
    {
        self::assertSame('abc', (new RadixSpelling())->digits((new RadixSpelling())->hexadecimal('abc')));
        self::assertSame('00AB', (new RadixSpelling())->digits((new RadixSpelling())->hexadecimal('00AB')));
        self::assertSame('', (new RadixSpelling())->digits((new RadixSpelling())->hexadecimal('')));
    }

    public function testBitsQuotesAnyCountOfDigits(): void
    {
        self::assertSame("b'101'", (new RadixSpelling())->bits('101'));
        self::assertSame("b'1'", (new RadixSpelling())->bits('1'));
        self::assertSame("b''", (new RadixSpelling())->bits(''));
        self::assertSame('0011', (new RadixSpelling())->digits((new RadixSpelling())->bits('0011')));
    }
}
