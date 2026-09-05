<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Bison\Lexer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\SourceCursor;
use SqlFaker\MySql\Bison\Lexer\BisonLexeme;
use SqlFaker\MySql\Bison\Lexer\BisonToken;
use SqlFaker\MySql\Bison\Lexer\QuotedLiteralScanner;

#[CoversClass(QuotedLiteralScanner::class)]
#[UsesClass(BisonLexeme::class)]
#[UsesClass(BisonToken::class)]
#[UsesClass(SourceCursor::class)]
final class QuotedLiteralScannerTest extends TestCase
{
    public function testHandlesClaimsBothQuoteCharacters(): void
    {
        $scanner = new QuotedLiteralScanner();

        self::assertTrue($scanner->handles("'"));
        self::assertTrue($scanner->handles('"'));
        self::assertFalse($scanner->handles('`'));
    }

    public function testScanReadsSingleQuotesAsACharacterLiteral(): void
    {
        $cursor = new SourceCursor("'+' rest");

        $token = (new QuotedLiteralScanner())->scan($cursor);

        self::assertSame(BisonLexeme::CharLiteral, $token->type);
        self::assertSame('+', $token->value);
        self::assertSame(0, $token->offset);
        self::assertSame(' rest', $cursor->takeRest());
    }

    public function testScanReadsDoubleQuotesAsAStringLiteral(): void
    {
        $cursor = new SourceCursor('"alias" rest');

        $token = (new QuotedLiteralScanner())->scan($cursor);

        self::assertSame(BisonLexeme::StringLiteral, $token->type);
        self::assertSame('alias', $token->value);
        self::assertSame(' rest', $cursor->takeRest());
    }

    public function testScanKeepsReadingPastAnEscapedQuote(): void
    {
        $cursor = new SourceCursor('"a\\"b" rest');

        self::assertSame('a"b', (new QuotedLiteralScanner())->scan($cursor)->value);
        self::assertSame(' rest', $cursor->takeRest());
    }

    public function testScanReadsAnEmptyLiteral(): void
    {
        $cursor = new SourceCursor("''");

        self::assertSame('', (new QuotedLiteralScanner())->scan($cursor)->value);
    }
}
