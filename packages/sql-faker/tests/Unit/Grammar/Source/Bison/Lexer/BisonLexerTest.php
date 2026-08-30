<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Source\Bison\Lexer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Source\Bison\Lexer\ActionScanner;
use SqlFaker\Grammar\Source\Bison\Lexer\BisonLexeme;
use SqlFaker\Grammar\Source\Bison\Lexer\BisonLexer;
use SqlFaker\Grammar\Source\Bison\Lexer\BisonScannerChain;
use SqlFaker\Grammar\Source\Bison\Lexer\BisonToken;
use SqlFaker\Grammar\Source\Bison\Lexer\BisonTrivia;
use SqlFaker\Grammar\Source\Bison\Lexer\DirectiveScanner;
use SqlFaker\Grammar\Source\Bison\Lexer\IdentifierScanner;
use SqlFaker\Grammar\Source\Bison\Lexer\NumberScanner;
use SqlFaker\Grammar\Source\Bison\Lexer\PunctuationScanner;
use SqlFaker\Grammar\Source\Bison\Lexer\QuotedLiteralScanner;
use SqlFaker\Grammar\Source\Bison\Lexer\TypeTagScanner;
use SqlFaker\Grammar\Source\GrammarParseException;
use SqlFaker\Grammar\Source\SourceCursor;
use Tests\Fixture\SqlFaker\StuckScanner;

#[CoversClass(BisonLexer::class)]
#[UsesClass(ActionScanner::class)]
#[UsesClass(BisonLexeme::class)]
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
final class BisonLexerTest extends TestCase
{
    public function testScanReadsAnEmptySourceAsEndOfFile(): void
    {
        $token = (new BisonLexer(''))->scan();

        self::assertSame(BisonLexeme::Eof, $token->type);
        self::assertSame('', $token->value);
        self::assertSame(0, $token->offset);
    }

    public function testScanKeepsReportingEndOfFile(): void
    {
        $lexer = new BisonLexer('a');

        $lexer->scan();

        self::assertSame(BisonLexeme::Eof, $lexer->scan()->type);
        self::assertSame(BisonLexeme::Eof, $lexer->scan()->type);
    }

    public function testScanExcludesLeadingWhitespaceFromTheToken(): void
    {
        $token = (new BisonLexer("  \n foo"))->scan();

        self::assertSame(BisonLexeme::Identifier, $token->type);
        self::assertSame(4, $token->offset);
    }

    public function testScanSkipsRunsOfMixedTrivia(): void
    {
        $token = (new BisonLexer('/* a */ // b' . "\n" . '/* c */ foo'))->scan();

        self::assertSame(BisonLexeme::Identifier, $token->type);
        self::assertSame('foo', $token->value);
    }

    public function testScanReadsTrailingTriviaAsEndOfFile(): void
    {
        self::assertSame(BisonLexeme::Eof, (new BisonLexer('/* only a comment */'))->scan()->type);
    }

    public function testScanAdvancesToTheNextTokenEachTime(): void
    {
        $lexer = new BisonLexer('a : b ;');

        $lexemes = [
            $lexer->scan()->type,
            $lexer->scan()->type,
            $lexer->scan()->type,
            $lexer->scan()->type,
            $lexer->scan()->type,
        ];

        self::assertSame(
            [
                BisonLexeme::Identifier,
                BisonLexeme::Colon,
                BisonLexeme::Identifier,
                BisonLexeme::Semicolon,
                BisonLexeme::Eof,
            ],
            $lexemes,
        );
    }

    public function testScanReportsACharacterNoScannerClaims(): void
    {
        $lexer = new BisonLexer('  @');

        $this->expectException(GrammarParseException::class);
        $this->expectExceptionMessage("Unexpected character '@' at offset 2");

        $lexer->scan();
    }

    public function testScanUsesTheChainItWasGiven(): void
    {
        $lexer = new BisonLexer('foo', new BisonScannerChain([new IdentifierScanner()]));

        self::assertSame('foo', $lexer->scan()->value);
    }

    public function testScanRejectsWhatTheGivenChainDoesNotCover(): void
    {
        $lexer = new BisonLexer('123', new BisonScannerChain([new IdentifierScanner()]));

        $this->expectException(GrammarParseException::class);
        $this->expectExceptionMessage("Unexpected character '1' at offset 0");

        $lexer->scan();
    }

    public function testConsumeRemainingTakesTheRestWithoutScanningIt(): void
    {
        $lexer = new BisonLexer('foo @ % <');

        $lexer->scan();

        self::assertSame(' @ % <', $lexer->consumeRemaining());
    }

    public function testConsumeRemainingIsEmptyOnceTheSourceRunsOut(): void
    {
        $lexer = new BisonLexer('foo');

        $lexer->scan();

        self::assertSame('', $lexer->consumeRemaining());
    }
    public function testScanRefusesAScannerThatReadsACharacterWithoutConsumingIt(): void
    {
        $lexer = new BisonLexer(';', new BisonScannerChain([new StuckScanner()]));

        $this->expectException(GrammarParseException::class);
        $this->expectExceptionMessage("Scanner read ';' at offset 0 without consuming it");

        $lexer->scan();
    }
}
