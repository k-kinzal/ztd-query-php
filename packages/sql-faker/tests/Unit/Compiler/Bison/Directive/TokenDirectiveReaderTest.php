<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Compiler\Bison\Directive;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Compiler\Bison\Ast\BisonTokenDeclaration;
use SqlFaker\Compiler\Bison\Ast\BisonTokenDefinition;
use SqlFaker\Compiler\Bison\Directive\BisonDeclarationBoundary;
use SqlFaker\Compiler\Bison\Directive\TokenDirectiveReader;
use SqlFaker\Compiler\Bison\Lexer\ActionScanner;
use SqlFaker\Compiler\Bison\Lexer\BisonLexeme;
use SqlFaker\Compiler\Bison\Lexer\BisonLexer;
use SqlFaker\Compiler\Bison\Lexer\BisonScannerChain;
use SqlFaker\Compiler\Bison\Lexer\BisonToken;
use SqlFaker\Compiler\Bison\Lexer\BisonTokenStream;
use SqlFaker\Compiler\Bison\Lexer\BisonTrivia;
use SqlFaker\Compiler\Bison\Lexer\DirectiveScanner;
use SqlFaker\Compiler\Bison\Lexer\IdentifierScanner;
use SqlFaker\Compiler\Bison\Lexer\NumberScanner;
use SqlFaker\Compiler\Bison\Lexer\QuotedLiteralScanner;
use SqlFaker\Compiler\Bison\Lexer\TypeTagScanner;
use SqlFaker\Grammar\SourceCursor;

#[CoversClass(TokenDirectiveReader::class)]
#[UsesClass(BisonDeclarationBoundary::class)]
#[UsesClass(BisonLexeme::class)]
#[UsesClass(BisonLexer::class)]
#[UsesClass(BisonLexer::class)]
#[UsesClass(BisonScannerChain::class)]
#[UsesClass(BisonToken::class)]
#[UsesClass(BisonTokenDeclaration::class)]
#[UsesClass(BisonTokenDefinition::class)]
#[UsesClass(BisonTokenStream::class)]
#[UsesClass(BisonTrivia::class)]
#[UsesClass(SourceCursor::class)]
#[UsesClass(ActionScanner::class)]
#[UsesClass(DirectiveScanner::class)]
#[UsesClass(IdentifierScanner::class)]
#[UsesClass(NumberScanner::class)]
#[UsesClass(QuotedLiteralScanner::class)]
#[UsesClass(TypeTagScanner::class)]
final class TokenDirectiveReaderTest extends TestCase
{
    public function testHandlesClaimsOnlyTheTokenDirective(): void
    {
        $reader = new TokenDirectiveReader();

        self::assertTrue($reader->handles('%token'));
        self::assertFalse($reader->handles('%type'));
    }

    public function testReadTakesEveryTerminalNamed(): void
    {
        $declaration = (new TokenDirectiveReader())
            ->read(BisonTokenStream::over('SELECT_SYM FROM_SYM WHERE_SYM'), '%token');

        self::assertInstanceOf(BisonTokenDeclaration::class, $declaration);
        self::assertSame(
            ['SELECT_SYM', 'FROM_SYM', 'WHERE_SYM'],
            array_map(static fn (BisonTokenDefinition $token): string => $token->name, $declaration->tokens),
        );
    }

    public function testReadTakesTheOptionalTypeTag(): void
    {
        $declaration = (new TokenDirectiveReader())->read(BisonTokenStream::over('<lexer> IDENT'), '%token');

        self::assertInstanceOf(BisonTokenDeclaration::class, $declaration);
        self::assertSame('lexer', $declaration->typeTag);
    }

    public function testReadReportsNoTypeTagWhenNoneIsWritten(): void
    {
        $declaration = (new TokenDirectiveReader())->read(BisonTokenStream::over('IDENT'), '%token');

        self::assertInstanceOf(BisonTokenDeclaration::class, $declaration);
        self::assertNull($declaration->typeTag);
    }

    public function testReadStopsAtTheNextDirective(): void
    {
        $stream = BisonTokenStream::over('IDENT %type <num> expr');

        (new TokenDirectiveReader())->read($stream, '%token');

        self::assertSame('%type', $stream->next()->value);
    }

    public function testReadTakesAnEmptyDeclarationAsNoTerminals(): void
    {
        $declaration = (new TokenDirectiveReader())->read(BisonTokenStream::over('%%'), '%token');

        self::assertInstanceOf(BisonTokenDeclaration::class, $declaration);
        self::assertSame([], $declaration->tokens);
    }

    public function testReadTerminalTakesTheNameAlone(): void
    {
        $token = (new TokenDirectiveReader())->readTerminal(BisonTokenStream::over('IDENT'));

        self::assertSame('IDENT', $token->name);
        self::assertNull($token->number);
        self::assertNull($token->alias);
    }

    public function testReadTerminalTakesTheExplicitTokenCode(): void
    {
        $token = (new TokenDirectiveReader())->readTerminal(BisonTokenStream::over('IDENT 258'));

        self::assertSame(258, $token->number);
        self::assertNull($token->alias);
    }

    public function testReadTerminalTakesTheAlias(): void
    {
        $token = (new TokenDirectiveReader())->readTerminal(BisonTokenStream::over('IDENT "identifier"'));

        self::assertNull($token->number);
        self::assertSame('identifier', $token->alias);
    }

    public function testReadTerminalTakesACodeAndAnAliasTogether(): void
    {
        $token = (new TokenDirectiveReader())->readTerminal(BisonTokenStream::over('IDENT 258 "identifier"'));

        self::assertSame('IDENT', $token->name);
        self::assertSame(258, $token->number);
        self::assertSame('identifier', $token->alias);
    }
}
