<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Bison\Directive;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\SourceCursor;
use SqlFaker\MySql\Bison\Ast\BisonUnknownDeclaration;
use SqlFaker\MySql\Bison\Directive\BisonDeclarationBoundary;
use SqlFaker\MySql\Bison\Directive\UnknownDirectiveReader;
use SqlFaker\MySql\Bison\Lexer\ActionScanner;
use SqlFaker\MySql\Bison\Lexer\BisonLexer;
use SqlFaker\MySql\Bison\Lexer\BisonScannerChain;
use SqlFaker\MySql\Bison\Lexer\BisonToken;
use SqlFaker\MySql\Bison\Lexer\BisonTokenStream;
use SqlFaker\MySql\Bison\Lexer\BisonTokenType;
use SqlFaker\MySql\Bison\Lexer\BisonTrivia;
use SqlFaker\MySql\Bison\Lexer\DirectiveScanner;
use SqlFaker\MySql\Bison\Lexer\IdentifierScanner;
use SqlFaker\MySql\Bison\Lexer\NumberScanner;
use SqlFaker\MySql\Bison\Lexer\QuotedLiteralScanner;
use SqlFaker\MySql\Bison\Lexer\TypeTagScanner;

#[CoversClass(UnknownDirectiveReader::class)]
#[UsesClass(BisonDeclarationBoundary::class)]
#[UsesClass(BisonTokenType::class)]
#[UsesClass(BisonLexer::class)]
#[UsesClass(\SqlFaker\MySql\Bison\Lexer\BisonTokenScanner::class)]
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
