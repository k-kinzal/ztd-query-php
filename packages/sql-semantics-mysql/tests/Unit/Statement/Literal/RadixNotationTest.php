<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Radix;
use SqlSemantics\Platform\MySql\Statement\Literal\RadixLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\RadixNotation;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;

#[CoversClass(RadixNotation::class)]
#[Small]
final class RadixNotationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, RadixNotation}>
     */
    public static function providerInvalidHexadecimalNotations(): iterable
    {
        yield 'empty prefixed value' => ['', RadixNotation::Prefixed];
        yield 'odd quoted digits' => ['A', RadixNotation::Quoted];
        yield 'odd upper quoted digits' => ['A', RadixNotation::UpperQuoted];
    }

    #[DataProvider('providerInvalidHexadecimalNotations')]
    public function testExpressionRejectsANotationThatCannotRepresentItsDigits(string $digits, RadixNotation $notation): void
    {
        $this->expectExceptionMessage('A radix notation accepts the literal digit count.');
        new RadixLiteral(Radix::Hexadecimal, $digits, notation: $notation);
    }

    #[DataProvider('providerInvalidHexadecimalNotations')]
    public function testTextRejectsANotationThatCannotRepresentItsDigits(string $digits, RadixNotation $notation): void
    {
        $this->expectExceptionMessage('A radix notation accepts the literal digit count.');
        new Text($digits, Radix::Hexadecimal, notation: $notation);
    }

    #[DataProvider('providerInvalidHexadecimalNotations')]
    public function testNumeralRejectsANotationThatCannotRepresentItsDigits(string $digits, RadixNotation $notation): void
    {
        $this->expectExceptionMessage('A radix notation accepts the literal digit count.');
        new Numeral($digits, true, notation: $notation);
    }

    public function testEmptyBitValueRequiresQuotes(): void
    {
        $this->expectExceptionMessage('A radix notation accepts the literal digit count.');
        new RadixLiteral(Radix::Bit, '', notation: RadixNotation::Prefixed);
    }

    public function testCharacterTextHasNoRadixNotation(): void
    {
        $this->expectExceptionMessage('Only radix values have a radix notation.');
        new Text('text', notation: RadixNotation::Quoted);
    }

    public function testDecimalNumeralHasNoRadixNotation(): void
    {
        $this->expectExceptionMessage('Only radix values have a radix notation.');
        new Numeral('12', notation: RadixNotation::Prefixed);
    }

    public function testCanonicalUsesThePrefixOnlyForOddHexadecimalDigits(): void
    {
        self::assertSame([RadixNotation::Prefixed, RadixNotation::Quoted, RadixNotation::Quoted, RadixNotation::Quoted], [RadixNotation::canonical(Radix::Hexadecimal, 'A'), RadixNotation::canonical(Radix::Hexadecimal, 'AB'), RadixNotation::canonical(Radix::Hexadecimal, ''), RadixNotation::canonical(Radix::Bit, '1')]);
    }

    public function testPermitsRequiresDigitsForAPrefixAndPairsForQuotedHexadecimal(): void
    {
        self::assertFalse(RadixNotation::Prefixed->permits(Radix::Bit, ''));
        self::assertFalse(RadixNotation::UpperQuoted->permits(Radix::Hexadecimal, 'A'));
        self::assertTrue(RadixNotation::Quoted->permits(Radix::Hexadecimal, ''));
        self::assertTrue(RadixNotation::Prefixed->permits(Radix::Hexadecimal, 'A'));
        self::assertTrue(RadixNotation::UpperQuoted->permits(Radix::Bit, '1'));
    }

    public function testSpellRetainsTheSelectedPrefixCaseAndDigitCase(): void
    {
        self::assertSame(['0x01aF', "X'01aF'", "x'01aF'", '0b01', "B'01'", "b'01'"], [RadixNotation::Prefixed->spell(Radix::Hexadecimal, '01aF'), RadixNotation::UpperQuoted->spell(Radix::Hexadecimal, '01aF'), RadixNotation::Quoted->spell(Radix::Hexadecimal, '01aF'), RadixNotation::Prefixed->spell(Radix::Bit, '01'), RadixNotation::UpperQuoted->spell(Radix::Bit, '01'), RadixNotation::Quoted->spell(Radix::Bit, '01')]);
    }
}
