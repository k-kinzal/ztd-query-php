<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Source\Bison\Directive;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Source\Bison\Ast\BisonExpectDeclaration;
use SqlFaker\Grammar\Source\Bison\Directive\BisonDeclarationBoundary;
use SqlFaker\Grammar\Source\Bison\Directive\ExpectDirectiveReader;
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

#[CoversClass(ExpectDirectiveReader::class)]
#[UsesClass(BisonDeclarationBoundary::class)]
#[UsesClass(BisonLexeme::class)]
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
