<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Bison;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\SourceCursor;
use SqlFaker\MySql\Bison\Ast\BisonStartDeclaration;
use SqlFaker\MySql\Bison\Ast\BisonTokenDeclaration;
use SqlFaker\MySql\Bison\Ast\BisonTokenDefinition;
use SqlFaker\MySql\Bison\Ast\BisonUnknownDeclaration;
use SqlFaker\MySql\Bison\BisonPreamble;
use SqlFaker\MySql\Bison\BisonPreambleReader;
use SqlFaker\MySql\Bison\Directive\BisonDeclarationBoundary;
use SqlFaker\MySql\Bison\Directive\BisonDirectiveReaderChain;
use SqlFaker\MySql\Bison\Directive\DefineDirectiveReader;
use SqlFaker\MySql\Bison\Directive\ExpectDirectiveReader;
use SqlFaker\MySql\Bison\Directive\ParamDirectiveReader;
use SqlFaker\MySql\Bison\Directive\PrecedenceDirectiveReader;
use SqlFaker\MySql\Bison\Directive\StartDirectiveReader;
use SqlFaker\MySql\Bison\Directive\TokenDirectiveReader;
use SqlFaker\MySql\Bison\Directive\TypeDirectiveReader;
use SqlFaker\MySql\Bison\Directive\UnknownDirectiveReader;
use SqlFaker\MySql\Bison\Lexer\BisonLexeme;
use SqlFaker\MySql\Bison\Lexer\BisonLexer;
use SqlFaker\MySql\Bison\Lexer\BisonScannerChain;
use SqlFaker\MySql\Bison\Lexer\BisonToken;
use SqlFaker\MySql\Bison\Lexer\BisonTokenStream;
use SqlFaker\MySql\Bison\Lexer\BisonTrivia;

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
