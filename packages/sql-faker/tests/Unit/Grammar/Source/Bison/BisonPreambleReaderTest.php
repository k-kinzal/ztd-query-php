<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Source\Bison;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Source\Bison\Ast\BisonStartDeclaration;
use SqlFaker\Grammar\Source\Bison\Ast\BisonTokenDeclaration;
use SqlFaker\Grammar\Source\Bison\Ast\BisonTokenDefinition;
use SqlFaker\Grammar\Source\Bison\Ast\BisonUnknownDeclaration;
use SqlFaker\Grammar\Source\Bison\BisonPreamble;
use SqlFaker\Grammar\Source\Bison\BisonPreambleReader;
use SqlFaker\Grammar\Source\Bison\Directive\BisonDeclarationBoundary;
use SqlFaker\Grammar\Source\Bison\Directive\BisonDirectiveReaderChain;
use SqlFaker\Grammar\Source\Bison\Directive\DefineDirectiveReader;
use SqlFaker\Grammar\Source\Bison\Directive\ExpectDirectiveReader;
use SqlFaker\Grammar\Source\Bison\Directive\ParamDirectiveReader;
use SqlFaker\Grammar\Source\Bison\Directive\PrecedenceDirectiveReader;
use SqlFaker\Grammar\Source\Bison\Directive\StartDirectiveReader;
use SqlFaker\Grammar\Source\Bison\Directive\TokenDirectiveReader;
use SqlFaker\Grammar\Source\Bison\Directive\TypeDirectiveReader;
use SqlFaker\Grammar\Source\Bison\Directive\UnknownDirectiveReader;
use SqlFaker\Grammar\Source\Bison\Lexer\ActionScanner;
use SqlFaker\Grammar\Source\Bison\Lexer\BisonLexeme;
use SqlFaker\Grammar\Source\Bison\Lexer\BisonLexer;
use SqlFaker\Grammar\Source\Bison\Lexer\BisonScannerChain;
use SqlFaker\Grammar\Source\Bison\Lexer\BisonToken;
use SqlFaker\Grammar\Source\Bison\Lexer\BisonTokenStream;
use SqlFaker\Grammar\Source\Bison\Lexer\BisonTrivia;
use SqlFaker\Grammar\Source\Bison\Lexer\DirectiveScanner;
use SqlFaker\Grammar\Source\Bison\Lexer\IdentifierScanner;
use SqlFaker\Grammar\Source\Bison\Lexer\NumberScanner;
use SqlFaker\Grammar\Source\Bison\Lexer\QuotedLiteralScanner;
use SqlFaker\Grammar\Source\Bison\Lexer\TypeTagScanner;
use SqlFaker\Grammar\Source\SourceCursor;

#[CoversClass(BisonPreambleReader::class)]
#[UsesClass(BisonDeclarationBoundary::class)]
#[UsesClass(BisonDirectiveReaderChain::class)]
#[UsesClass(BisonLexeme::class)]
#[UsesClass(BisonLexer::class)]
#[UsesClass(BisonPreamble::class)]
#[UsesClass(BisonScannerChain::class)]
#[UsesClass(BisonStartDeclaration::class)]
#[UsesClass(BisonToken::class)]
#[UsesClass(BisonTokenDeclaration::class)]
#[UsesClass(BisonTokenDefinition::class)]
#[UsesClass(BisonTokenStream::class)]
#[UsesClass(BisonTrivia::class)]
#[UsesClass(BisonUnknownDeclaration::class)]
#[UsesClass(DefineDirectiveReader::class)]
#[UsesClass(ExpectDirectiveReader::class)]
#[UsesClass(ParamDirectiveReader::class)]
#[UsesClass(PrecedenceDirectiveReader::class)]
#[UsesClass(SourceCursor::class)]
#[UsesClass(StartDirectiveReader::class)]
#[UsesClass(TokenDirectiveReader::class)]
#[UsesClass(TypeDirectiveReader::class)]
#[UsesClass(UnknownDirectiveReader::class)]
#[UsesClass(ActionScanner::class)]
#[UsesClass(DirectiveScanner::class)]
#[UsesClass(IdentifierScanner::class)]
#[UsesClass(NumberScanner::class)]
#[UsesClass(QuotedLiteralScanner::class)]
#[UsesClass(TypeTagScanner::class)]
final class BisonPreambleReaderTest extends TestCase
{
    public function testReadTakesThePrologueAndTheDeclarations(): void
    {
        $preamble = (new BisonPreambleReader())->read(
            BisonTokenStream::over("%{ int x; %}\n%start statement\n%token IDENT\n%%"),
        );

        self::assertSame(' int x; ', $preamble->prologue);
        self::assertCount(2, $preamble->declarations);
        self::assertInstanceOf(BisonStartDeclaration::class, $preamble->declarations[0]);
        self::assertInstanceOf(BisonTokenDeclaration::class, $preamble->declarations[1]);
    }

    public function testReadReportsNoPrologueWhenTheFileHasNone(): void
    {
        $preamble = (new BisonPreambleReader())->read(BisonTokenStream::over("%start statement\n%%"));

        self::assertNull($preamble->prologue);
    }

    public function testReadConsumesTheSectionSeparatorSoTheRulesFollow(): void
    {
        $stream = BisonTokenStream::over("%start statement\n%%\nexpr : a ;");

        (new BisonPreambleReader())->read($stream);

        self::assertSame('expr', $stream->next()->value);
    }

    public function testReadStopsAtTheEndOfAFileWithoutASeparator(): void
    {
        $preamble = (new BisonPreambleReader())->read(BisonTokenStream::over('%start statement'));

        self::assertCount(1, $preamble->declarations);
    }

    public function testReadDropsADeclarationWhoseArgumentsDoNotFitItsDirective(): void
    {
        $preamble = (new BisonPreambleReader())->read(BisonTokenStream::over("%start\n%%"));

        self::assertSame([], $preamble->declarations);
    }

    public function testReadKeepsADirectiveItHasNoModelFor(): void
    {
        $preamble = (new BisonPreambleReader())->read(BisonTokenStream::over("%glr-parser\n%%"));

        self::assertCount(1, $preamble->declarations);
        self::assertInstanceOf(BisonUnknownDeclaration::class, $preamble->declarations[0]);
    }

    public function testReadKeepsTheLastPrologueWhenAFileHasSeveral(): void
    {
        $preamble = (new BisonPreambleReader())->read(
            BisonTokenStream::over('%{ first %} %{ second %} %%'),
        );

        self::assertSame(' second ', $preamble->prologue);
    }

    public function testReadUsesTheDirectiveChainItWasGiven(): void
    {
        $reader = new BisonPreambleReader(new BisonDirectiveReaderChain([new StartDirectiveReader()]));

        $preamble = $reader->read(BisonTokenStream::over("%start statement\n%glr-parser\n%%"));

        self::assertCount(1, $preamble->declarations);
        self::assertInstanceOf(BisonStartDeclaration::class, $preamble->declarations[0]);
    }
}
