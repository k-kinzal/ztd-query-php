<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Lexical;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Rules\Lexical\Strings;

#[CoversClass(Strings::class)]
#[Small]
final class StringsTest extends TestCase
{
    public function testDecodeReadsADoubledQuoteOfAStandardString(): void
    {
        self::assertSame("a'b\\n", (new Strings())->decode("'a''b\\n'"));
    }

    public function testDecodeReadsTheEscapesOfAnEscapeString(): void
    {
        self::assertSame("a'b\n\tAAé😀\\q", (new Strings())->decode("E'a\\'b\\n\\t\\x41\\101\\u00e9\\U0001F600\\\\\\q'"));
    }

    public function testDecodeJoinsASurrogatePairOfAnEscapeString(): void
    {
        self::assertSame('😀', (new Strings())->decode("e'\\uD83D\\uDE00'"));
    }

    public function testDecodeTakesADollarQuotedStringLiterally(): void
    {
        self::assertSame("a'\\\$\$b", (new Strings())->decode("\$t\$a'\\\$\$b\$t\$"));
    }

    public function testDecodeReplacesTheEscapesOfAUnicodeString(): void
    {
        self::assertSame("data'", (new Strings())->decode("U&'d\\0061t\\+000061'''"));
        self::assertSame('data', (new Strings())->decode("u&'d!0061t!+000061' UESCAPE '!'"));
    }

    public function testDecodeJoinsSegmentsContinuedOnAnotherLine(): void
    {
        self::assertSame("ab\tc", (new Strings())->decode("E'a' -- note\n  'b\\tc'"));
    }

    public function testDecodeRejectsAZeroByteFromAnEscape(): void
    {
        $this->expectException(AnalysisException::class);
        (new Strings())->decode("E'a\\000b'");
    }

    public function testDecodeRejectsBytesThatAreNotUtf8(): void
    {
        $this->expectException(AnalysisException::class);
        (new Strings())->decode("E'\\xff'");
    }

    public function testDigitsAnswersTheTextBetweenTheQuotes(): void
    {
        self::assertSame('1F', (new Strings())->digits("X'1F'"));
        self::assertSame('1001', (new Strings())->digits("b'10'\n'01'"));
    }

    public function testQuotedAnswersTheValueAndTheTextAfterIt(): void
    {
        self::assertSame(["a'b", " UESCAPE '!'"], (new Strings())->quoted("U&'a''b' UESCAPE '!'", 2, false));
    }

    public function testContinuationFindsTheNextQuoteAfterANewline(): void
    {
        self::assertSame(5, (new Strings())->continuation("'a'\n 'b'", 3));
        self::assertNull((new Strings())->continuation("'a' UESCAPE '!'", 3));
    }

    public function testEscapeDecodesOneBackslashEscape(): void
    {
        self::assertSame(["\n", 2], (new Strings())->escape('\\n', 0));
        self::assertSame(['A', 4], (new Strings())->escape('\\x41', 0));
        self::assertSame(['S', 4], (new Strings())->escape('\\123', 0));
        self::assertSame(['q', 2], (new Strings())->escape('\\q', 0));
    }

    public function testEscapeRejectsAnIncompleteUnicodeEscape(): void
    {
        $this->expectException(AnalysisException::class);
        (new Strings())->escape('\\u12', 0);
    }
}
