<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Source\Bison\Directive;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Source\Bison\Ast\BisonUnknownDeclaration;
use SqlFaker\Grammar\Source\Bison\Directive\BisonDeclarationBoundary;
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

#[CoversClass(UnknownDirectiveReader::class)]
#[UsesClass(BisonDeclarationBoundary::class)]
#[UsesClass(BisonLexeme::class)]
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
