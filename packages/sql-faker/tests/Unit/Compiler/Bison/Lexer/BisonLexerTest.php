<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Compiler\Bison\Lexer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Compiler\Bison\Lexer\ActionScanner;
use SqlFaker\Compiler\Bison\Lexer\BisonLexeme;
use SqlFaker\Compiler\Bison\Lexer\BisonLexer;
use SqlFaker\Compiler\Bison\Lexer\BisonScannerChain;
use SqlFaker\Compiler\Bison\Lexer\BisonToken;
use SqlFaker\Compiler\Bison\Lexer\BisonTrivia;
use SqlFaker\Compiler\Bison\Lexer\DirectiveScanner;
use SqlFaker\Compiler\Bison\Lexer\IdentifierScanner;
use SqlFaker\Compiler\Bison\Lexer\NumberScanner;
use SqlFaker\Compiler\Bison\Lexer\PunctuationScanner;
use SqlFaker\Compiler\Bison\Lexer\QuotedLiteralScanner;
use SqlFaker\Compiler\Bison\Lexer\TypeTagScanner;
use SqlFaker\Grammar\GrammarParseException;
use SqlFaker\Grammar\Lexical\SourceCursor;

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
}
