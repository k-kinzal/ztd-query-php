<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Compiler\Bison\Directive;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Compiler\Bison\Ast\BisonUnknownDeclaration;
use SqlFaker\Compiler\Bison\Directive\BisonDeclarationBoundary;
use SqlFaker\Compiler\Bison\Directive\UnknownDirectiveReader;
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
use SqlFaker\Grammar\Lexical\SourceCursor;

#[CoversClass(UnknownDirectiveReader::class)]
#[UsesClass(BisonDeclarationBoundary::class)]
#[UsesClass(BisonLexeme::class)]
#[UsesClass(BisonLexer::class)]
#[UsesClass(BisonLexer::class)]
#[UsesClass(BisonScannerChain::class)]
#[UsesClass(BisonToken::class)]
#[UsesClass(BisonTokenStream::class)]
#[UsesClass(BisonTrivia::class)]
#[UsesClass(BisonUnknownDeclaration::class)]
#[UsesClass(SourceCursor::class)]
#[UsesClass(ActionScanner::class)]
#[UsesClass(DirectiveScanner::class)]
#[UsesClass(IdentifierScanner::class)]
#[UsesClass(NumberScanner::class)]
#[UsesClass(QuotedLiteralScanner::class)]
#[UsesClass(TypeTagScanner::class)]
final class UnknownDirectiveReaderTest extends TestCase
{
    public function testHandlesAcceptsAnyDirective(): void
    {
        $reader = new UnknownDirectiveReader();

        self::assertTrue($reader->handles('%pure-parser'));
        self::assertTrue($reader->handles('%token'));
    }

    public function testReadKeepsTheArgumentsAsText(): void
    {
        $declaration = (new UnknownDirectiveReader())
            ->read(BisonTokenStream::over('a b c'), '%pure-parser');

        self::assertInstanceOf(BisonUnknownDeclaration::class, $declaration);
        self::assertSame('%pure-parser', $declaration->directive);
        self::assertSame('a b c', $declaration->content);
    }

    public function testReadKeepsTheDirectiveWithoutArguments(): void
    {
        $declaration = (new UnknownDirectiveReader())->read(BisonTokenStream::over('%%'), '%glr-parser');

        self::assertInstanceOf(BisonUnknownDeclaration::class, $declaration);
        self::assertSame('', $declaration->content);
    }

    public function testReadLeavesTheStreamOnTheNextDeclaration(): void
    {
        $stream = BisonTokenStream::over('a b %token IDENT');

        (new UnknownDirectiveReader())->read($stream, '%pure-parser');

        self::assertSame('%token', $stream->next()->value);
    }
}
