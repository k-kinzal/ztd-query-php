<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Pattern;

use MySqlMemory\Evaluation\Function\Pattern\Escapes;
use MySqlMemory\Evaluation\Function\Pattern\Fragment;
use MySqlMemory\Evaluation\Function\Pattern\Mode;
use MySqlMemory\Evaluation\Function\Pattern\Scanner;
use MySqlMemory\Evaluation\Function\Pattern\Translator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Escapes::class)]
#[Small]
final class EscapesTest extends TestCase
{
    public function testEscapeRefusesABackslashAtTheEnd(): void
    {
        $this->expectExceptionMessage('Unrecognized escape sequence in regular expression.');

        (new Translator())->translate('a\\', new Mode());
    }

    public function testCodeAnswersTheCharacterOfAnEscape(): void
    {
        $translator = new Translator();
        $translator->scanner = Scanner::of('A');

        self::assertSame(["\x1B", "\x01", null], [$translator->escapes->code('e'), $translator->escapes->code('c'), $translator->escapes->code('y')]);
    }

    public function testHexadecimalReadsBracedOrTwoDigits(): void
    {
        $translator = new Translator();
        $translator->scanner = Scanner::of('{1F600}41');

        self::assertSame(['😀', 'A'], [$translator->escapes->hexadecimal(), $translator->escapes->hexadecimal()]);
    }

    public function testFixedReadsExactlyTheDigits(): void
    {
        $translator = new Translator();
        $translator->scanner = Scanner::of('00e9');

        self::assertSame('é', $translator->escapes->fixed(4));
    }

    public function testPointRefusesACodePointBeyondUnicode(): void
    {
        $this->expectExceptionMessage('Unrecognized escape sequence in regular expression.');

        (new Translator())->escapes->point('110000');
    }

    public function testOctalReadsUpToThreeDigits(): void
    {
        $translator = new Translator();
        $translator->scanner = Scanner::of('1417');

        self::assertSame('a', $translator->escapes->octal());
    }

    public function testNamedRefusesAnUnknownName(): void
    {
        $this->expectExceptionMessage("Got error 'U_ILLEGAL_CHAR_FOUND' from regexp");

        (new Translator())->translate('\N{FOO}', new Mode());
    }

    public function testBracedRefusesEmptyBraces(): void
    {
        $this->expectExceptionMessage('Illegal argument to a regular expression.');

        (new Translator())->translate('\p{}', new Mode());
    }

    public function testPropertyComplementsForAnUpperCaseLetter(): void
    {
        $translator = new Translator();
        $translator->scanner = Scanner::of('{L}');

        self::assertSame('[^\p{L}]', $translator->escapes->property(true)->source());
    }

    public function testClusterNotesThatTheLocaleIsRead(): void
    {
        $translator = new Translator();

        self::assertSame(['\X', true], [$translator->escapes->cluster()->source, $translator->located]);
    }

    public function testLabelRefusesAMissingName(): void
    {
        $this->expectExceptionMessage('A capture group has an invalid name.');

        (new Translator())->translate('\k{n}', new Mode());
    }

    public function testDigitsTakeAFurtherDigitWhileBelowTheGroupsOpened(): void
    {
        $this->expectExceptionMessage('Invalid back-reference in regular expression.');

        (new Translator())->translate('(a)(b)\12', new Mode());
    }

    public function testReferenceAnswersAPlaceholder(): void
    {
        $translator = new Translator();

        self::assertSame(["\0" . '0' . "\0", [['3', false]]], [$translator->escapes->reference('3', false)->source, $translator->references]);
    }

    public function testQuotedReadsUpToBackslashE(): void
    {
        $translator = new Translator();
        $translator->scanner = Scanner::of('.*\Ex');
        $fragments = $translator->escapes->quoted();

        self::assertSame([['\x{2E}', '\x{2A}'], 'x'], [array_map(static fn (Fragment $fragment): string => $fragment->source, $fragments), $translator->scanner->peek()]);
    }
}
