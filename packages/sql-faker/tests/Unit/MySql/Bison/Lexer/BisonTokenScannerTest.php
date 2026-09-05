<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Bison\Lexer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\GrammarParseException;
use SqlFaker\Grammar\SourceCursor;
use SqlFaker\MySql\Bison\Lexer\ActionScanner;
use SqlFaker\MySql\Bison\Lexer\BisonScannerChain;
use SqlFaker\MySql\Bison\Lexer\BisonToken;
use SqlFaker\MySql\Bison\Lexer\BisonTokenScanner;
use SqlFaker\MySql\Bison\Lexer\BisonTokenType;
use SqlFaker\MySql\Bison\Lexer\BisonTrivia;
use SqlFaker\MySql\Bison\Lexer\DirectiveScanner;
use SqlFaker\MySql\Bison\Lexer\IdentifierScanner;
use SqlFaker\MySql\Bison\Lexer\NumberScanner;
use SqlFaker\MySql\Bison\Lexer\PunctuationScanner;
use SqlFaker\MySql\Bison\Lexer\QuotedLiteralScanner;
use SqlFaker\MySql\Bison\Lexer\TypeTagScanner;

#[CoversClass(BisonTokenScanner::class)]
#[UsesClass(ActionScanner::class)]
#[UsesClass(BisonTokenType::class)]
#[UsesClass(BisonScannerChain::class)]
#[UsesClass(BisonToken::class)]
#[UsesClass(BisonTrivia::class)]
#[UsesClass(DirectiveScanner::class)]
#[UsesClass(GrammarParseException::class)]
#[UsesClass(IdentifierScanner::class)]
#[UsesClass(NumberScanner::class)]
#[UsesClass(PunctuationScanner::class)]
#[UsesClass(QuotedLiteralScanner::class)]
#[UsesClass(SourceCursor::class)]
#[UsesClass(TypeTagScanner::class)]
final class BisonTokenScannerTest extends TestCase
{
    public function testScanReadsAnEmptySourceAsEndOfFile(): void
    {
        $token = (new BisonTokenScanner(''))->scan();

        self::assertSame(BisonTokenType::Eof, $token->type);
        self::assertSame('', $token->value);
        self::assertSame(0, $token->offset);
    }

    public function testScanKeepsReportingEndOfFile(): void
    {
        $lexer = new BisonTokenScanner('a');

        $lexer->scan();

        self::assertSame(BisonTokenType::Eof, $lexer->scan()->type);
        self::assertSame(BisonTokenType::Eof, $lexer->scan()->type);
    }

    public function testScanExcludesLeadingWhitespaceFromTheToken(): void
    {
        $token = (new BisonTokenScanner("  \n foo"))->scan();

        self::assertSame(BisonTokenType::Identifier, $token->type);
        self::assertSame(4, $token->offset);
    }

    public function testScanSkipsRunsOfMixedTrivia(): void
    {
        $token = (new BisonTokenScanner('/* a */ // b' . "\n" . '/* c */ foo'))->scan();

        self::assertSame(BisonTokenType::Identifier, $token->type);
        self::assertSame('foo', $token->value);
    }

    public function testScanReadsTrailingTriviaAsEndOfFile(): void
    {
        self::assertSame(BisonTokenType::Eof, (new BisonTokenScanner('/* only a comment */'))->scan()->type);
    }

    public function testScanAdvancesToTheNextTokenEachTime(): void
    {
        $lexer = new BisonTokenScanner('a : b ;');

        $lexemes = [
            $lexer->scan()->type,
            $lexer->scan()->type,
            $lexer->scan()->type,
            $lexer->scan()->type,
            $lexer->scan()->type,
        ];

        self::assertSame(
            [
                BisonTokenType::Identifier,
                BisonTokenType::Colon,
                BisonTokenType::Identifier,
                BisonTokenType::Semicolon,
                BisonTokenType::Eof,
            ],
            $lexemes,
        );
    }

    public function testScanReportsACharacterNoScannerClaims(): void
    {
        $lexer = new BisonTokenScanner('  @');

        $this->expectException(GrammarParseException::class);
        $this->expectExceptionMessage("Unexpected character '@' at offset 2");

        $lexer->scan();
    }

    public function testScanUsesTheChainItWasGiven(): void
    {
        $lexer = new BisonTokenScanner('foo', new BisonScannerChain([new IdentifierScanner()]));

        self::assertSame('foo', $lexer->scan()->value);
    }

    public function testScanRejectsWhatTheGivenChainDoesNotCover(): void
    {
        $lexer = new BisonTokenScanner('123', new BisonScannerChain([new IdentifierScanner()]));

        $this->expectException(GrammarParseException::class);
        $this->expectExceptionMessage("Unexpected character '1' at offset 0");

        $lexer->scan();
    }

    public function testConsumeRemainingTakesTheRestWithoutScanningIt(): void
    {
        $lexer = new BisonTokenScanner('foo @ % <');

        $lexer->scan();

        self::assertSame(' @ % <', $lexer->consumeRemaining());
    }

    public function testConsumeRemainingIsEmptyOnceTheSourceRunsOut(): void
    {
        $lexer = new BisonTokenScanner('foo');

        $lexer->scan();

        self::assertSame('', $lexer->consumeRemaining());
    }
}
