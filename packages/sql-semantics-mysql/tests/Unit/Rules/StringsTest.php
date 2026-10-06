<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Strings;

#[CoversClass(Strings::class)]
#[Small]
final class StringsTest extends TestCase
{
    public function testDecodeReadsEveryEscapeLetterWhenBackslashEscapes(): void
    {
        self::assertSame("\0", (new Strings())->decode("'\\0'", true));
        self::assertSame("\x08", (new Strings())->decode("'\\b'", true));
        self::assertSame("\n", (new Strings())->decode("'\\n'", true));
        self::assertSame("\r", (new Strings())->decode("'\\r'", true));
        self::assertSame("\t", (new Strings())->decode("'\\t'", true));
        self::assertSame("\x1A", (new Strings())->decode("'\\Z'", true));
        self::assertSame('\\%', (new Strings())->decode("'\\%'", true));
        self::assertSame('\\_', (new Strings())->decode("'\\_'", true));
        self::assertSame('\\', (new Strings())->decode("'\\\\'", true));
        self::assertSame("'", (new Strings())->decode("'\\''", true));
        self::assertSame('"', (new Strings())->decode("'\\\"'", true));
        self::assertSame('x', (new Strings())->decode("'\\x'", true));
        self::assertSame('N', (new Strings())->decode("'\\N'", true));
        self::assertSame("a\nb", (new Strings())->decode("'a\\nb'", true));
    }

    public function testDecodeKeepsTheBackslashUnderNoBackslashEscapes(): void
    {
        self::assertSame('\\0', (new Strings())->decode("'\\0'", false));
        self::assertSame('\\b', (new Strings())->decode("'\\b'", false));
        self::assertSame('\\n', (new Strings())->decode("'\\n'", false));
        self::assertSame('\\r', (new Strings())->decode("'\\r'", false));
        self::assertSame('\\t', (new Strings())->decode("'\\t'", false));
        self::assertSame('\\Z', (new Strings())->decode("'\\Z'", false));
        self::assertSame('\\%', (new Strings())->decode("'\\%'", false));
        self::assertSame('\\_', (new Strings())->decode("'\\_'", false));
        self::assertSame('\\\\', (new Strings())->decode("'\\\\'", false));
        self::assertSame('\\x', (new Strings())->decode("'\\x'", false));
    }

    public function testDecodeReadsADoubledQuoteAsOne(): void
    {
        self::assertSame("a'b", (new Strings())->decode("'a''b'", true));
        self::assertSame("a'b", (new Strings())->decode("'a''b'", false));
        self::assertSame('a"b', (new Strings())->decode('"a""b"', true));
        self::assertSame('a"b', (new Strings())->decode('"a""b"', false));
        self::assertSame('a""b', (new Strings())->decode("'a\"\"b'", true));
        self::assertSame("''", (new Strings())->decode("''''''", false));
        self::assertSame('', (new Strings())->decode("''", true));
    }

    public function testDecodeAcceptsTheNationalPrefix(): void
    {
        self::assertSame('abc', (new Strings())->decode("N'abc'", true));
        self::assertSame('abc', (new Strings())->decode("n'abc'", false));
        self::assertSame("a'b\n", (new Strings())->decode("N'a''b\\n'", true));
        self::assertSame("a'b\\n", (new Strings())->decode("N'a''b\\n'", false));
    }

    public function testDecodeKeepsBytesAboveAsciiAsWritten(): void
    {
        self::assertSame("caf\xC3\xA9", (new Strings())->decode("'caf\xC3\xA9'", true));
        self::assertSame("\xE3\x81\x82\\", (new Strings())->decode("'\xE3\x81\x82\\'", false));
    }

    public function testEncodeWritesSingleQuotesAndDoublesWhatWouldEndThem(): void
    {
        self::assertSame("'abc'", (new Strings())->encode('abc', true));
        self::assertSame("'a''b'", (new Strings())->encode("a'b", true));
        self::assertSame("'a''b'", (new Strings())->encode("a'b", false));
        self::assertSame("'a\\\\b'", (new Strings())->encode('a\\b', true));
        self::assertSame("'a\\b'", (new Strings())->encode('a\\b', false));
        self::assertSame("'a\"b'", (new Strings())->encode('a"b', true));
        self::assertSame("''", (new Strings())->encode('', false));
    }

    public function testEncodeRoundTripsThroughDecodeUnderBothSettings(): void
    {
        self::assertSame("it's", (new Strings())->decode((new Strings())->encode("it's", true), true));
        self::assertSame("it's", (new Strings())->decode((new Strings())->encode("it's", false), false));
        self::assertSame('a\\b\\\\c', (new Strings())->decode((new Strings())->encode('a\\b\\\\c', true), true));
        self::assertSame('a\\b\\\\c', (new Strings())->decode((new Strings())->encode('a\\b\\\\c', false), false));
        self::assertSame("a\0b", (new Strings())->decode((new Strings())->encode("a\0b", true), true));
        self::assertSame("a\0b", (new Strings())->decode((new Strings())->encode("a\0b", false), false));
        self::assertSame("a\nb\r\n", (new Strings())->decode((new Strings())->encode("a\nb\r\n", true), true));
        self::assertSame("a\nb\r\n", (new Strings())->decode((new Strings())->encode("a\nb\r\n", false), false));
        self::assertSame("\\'\\n'", (new Strings())->decode((new Strings())->encode("\\'\\n'", true), true));
        self::assertSame("\\'\\n'", (new Strings())->decode((new Strings())->encode("\\'\\n'", false), false));
        self::assertSame('\\', (new Strings())->decode((new Strings())->encode('\\', true), true));
        self::assertSame('\\', (new Strings())->decode((new Strings())->encode('\\', false), false));
    }

    public function testEncodeSpellsControlBytesAsEscapesWhenBackslashEscapes(): void
    {
        self::assertSame("'a\\0b\\nc\\rd\\Ze'", (new Strings())->encode("a\0b\nc\rd\x1Ae", true));
        self::assertSame("'a\0b\nc\rd\x1Ae'", (new Strings())->encode("a\0b\nc\rd\x1Ae", false));
        self::assertSame("'\tb'", (new Strings())->encode("\tb", true));
        self::assertSame("a\0b\nc\rd\x1Ae", (new Strings())->decode((new Strings())->encode("a\0b\nc\rd\x1Ae", true), true));
    }
}
