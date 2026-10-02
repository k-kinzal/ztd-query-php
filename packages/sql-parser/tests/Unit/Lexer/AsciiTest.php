<?php

declare(strict_types=1);

namespace Tests\Unit\Lexer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Ascii;

#[CoversClass(Ascii::class)]
#[Small]
final class AsciiTest extends TestCase
{
    #[TestWith(['IDENTIFIER_012', 'identifier_012'])]
    #[TestWith(['IİıiÄä', 'iİıiÄä'])]
    #[TestWith(["\xDDI\0A\xFF", "\xDDi\0a\xFF"])]
    public function testLowerLeavesNonAsciiBytesUntouched(string $input, string $expected): void
    {
        self::assertSame($expected, Ascii::lower($input));
    }

    #[TestWith(['is distinct from', 'IS DISTINCT FROM'])]
    #[TestWith(['IİıiÄä', 'IİıIÄä'])]
    #[TestWith(["\xFDi\0z\xFF", "\xFDI\0Z\xFF"])]
    public function testUpperRecognizesAsciiKeywordsWithoutLocaleFolding(string $input, string $expected): void
    {
        self::assertSame($expected, Ascii::upper($input));
    }

    #[TestWith(['', false])]
    #[TestWith(['A', true])]
    #[TestWith(['z', true])]
    #[TestWith(['0', false])]
    #[TestWith(['[', false])]
    #[TestWith(['é', false])]
    #[TestWith(["\xDD", false])]
    #[TestWith(['ab', false])]
    public function testLetterRecognizesOnlyOneAsciiAlphabeticByte(string $byte, bool $expected): void
    {
        self::assertSame($expected, Ascii::letter($byte));
    }

    #[TestWith(['', false])]
    #[TestWith(['0', true])]
    #[TestWith(['9', true])]
    #[TestWith(['/', false])]
    #[TestWith([':', false])]
    #[TestWith(['٠', false])]
    #[TestWith(['00', false])]
    public function testDigitDistinguishesDecimalLookaheadFromOtherBytes(string $byte, bool $expected): void
    {
        self::assertSame($expected, Ascii::digit($byte));
    }

    #[TestWith(['', false])]
    #[TestWith([' ', true])]
    #[TestWith(["\t", true])]
    #[TestWith(["\n", true])]
    #[TestWith(["\v", true])]
    #[TestWith(["\xA0", false])]
    #[TestWith(["\0", false])]
    public function testSpaceKeepsSqlWhitespaceIndependentOfHostLocale(string $byte, bool $expected): void
    {
        self::assertSame($expected, Ascii::space($byte));
    }

    #[TestWith(['', false])]
    #[TestWith(["\0", true])]
    #[TestWith(["\x1F", true])]
    #[TestWith(["\x7F", true])]
    #[TestWith([' ', false])]
    #[TestWith(["\x80", false])]
    public function testControlRecognizesAsciiControlBytesAndDel(string $byte, bool $expected): void
    {
        self::assertSame($expected, Ascii::control($byte));
    }
}
