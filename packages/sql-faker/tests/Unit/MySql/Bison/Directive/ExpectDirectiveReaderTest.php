<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Bison\Directive;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\SourceCursor;
use SqlFaker\MySql\Bison\Ast\BisonExpectDeclaration;
use SqlFaker\MySql\Bison\Directive\BisonDeclarationBoundary;
use SqlFaker\MySql\Bison\Directive\ExpectDirectiveReader;
use SqlFaker\MySql\Bison\Lexer\ActionScanner;
use SqlFaker\MySql\Bison\Lexer\BisonLexeme;
use SqlFaker\MySql\Bison\Lexer\BisonLexer;
use SqlFaker\MySql\Bison\Lexer\BisonScannerChain;
use SqlFaker\MySql\Bison\Lexer\BisonToken;
use SqlFaker\MySql\Bison\Lexer\BisonTokenStream;
use SqlFaker\MySql\Bison\Lexer\BisonTrivia;
use SqlFaker\MySql\Bison\Lexer\DirectiveScanner;
use SqlFaker\MySql\Bison\Lexer\IdentifierScanner;
use SqlFaker\MySql\Bison\Lexer\NumberScanner;
use SqlFaker\MySql\Bison\Lexer\QuotedLiteralScanner;
use SqlFaker\MySql\Bison\Lexer\TypeTagScanner;

#[CoversClass(ExpectDirectiveReader::class)]
#[UsesClass(BisonDeclarationBoundary::class)]
#[UsesClass(BisonLexeme::class)]
#[UsesClass(BisonLexer::class)]
#[UsesClass(BisonLexer::class)]
#[UsesClass(BisonScannerChain::class)]
#[UsesClass(BisonToken::class)]
#[UsesClass(BisonTokenStream::class)]
#[UsesClass(BisonTrivia::class)]
#[UsesClass(SourceCursor::class)]
#[UsesClass(BisonExpectDeclaration::class)]
#[UsesClass(ActionScanner::class)]
#[UsesClass(DirectiveScanner::class)]
#[UsesClass(IdentifierScanner::class)]
#[UsesClass(NumberScanner::class)]
#[UsesClass(QuotedLiteralScanner::class)]
#[UsesClass(TypeTagScanner::class)]
final class ExpectDirectiveReaderTest extends TestCase
{
    public function testHandlesClaimsOnlyTheExpectDirective(): void
    {
        $reader = new ExpectDirectiveReader();

        self::assertTrue($reader->handles('%expect'));
        self::assertFalse($reader->handles('%define'));
    }

    public function testReadTakesTheConflictCount(): void
    {
        $declaration = (new ExpectDirectiveReader())->read(BisonTokenStream::over('42'), '%expect');

        self::assertInstanceOf(BisonExpectDeclaration::class, $declaration);
        self::assertSame(42, $declaration->count);
    }

    public function testReadDeclaresNothingWhenNoNumberFollows(): void
    {
        self::assertNull((new ExpectDirectiveReader())->read(BisonTokenStream::over('foo'), '%expect'));
    }

    public function testReadLeavesATokenItDidNotClaim(): void
    {
        $stream = BisonTokenStream::over('foo');

        (new ExpectDirectiveReader())->read($stream, '%expect');

        self::assertSame('foo', $stream->next()->value);
    }
}
