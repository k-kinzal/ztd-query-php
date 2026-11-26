<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Binding\ProjectionMode;
use SqlSemantics\Platform\MySql\Ir\Literal\TextLiteral;

#[CoversClass(TextLiteral::class)]
#[Small]
final class TextLiteralTest extends TestCase
{
    public function testToStringRetainsDecodedBytesUnderEmptySqlMode(): void
    {
        $value = "𐐷\\'\0\x08\t\n\r\x1a";
        $literal = new TextLiteral($value);

        self::assertSame($value, $literal->value);
        self::assertSame("'𐐷\\\\''\\0\\b\\t\\n\\r\\Z'", $literal->toString());
    }

    public function testRejectsMalformedUtf8BeforePublication(): void
    {
        $this->expectExceptionMessage('A MySQL text literal requires valid UTF-8.');

        new TextLiteral("\xff");
    }

    public function testVerbatimModeDoublesOnlyTheQuoteDelimiter(): void
    {
        $literal = new TextLiteral("x\\'\0\n棚😀");

        self::assertSame("'x\\''\0\n棚😀'", $literal->toString(ProjectionMode::NoBackslashEscapes));
        self::assertSame("'x\\''\0\n棚😀'", $literal->toString(ProjectionMode::AnsiQuotesAndNoBackslashEscapes));
    }
}
