<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Compiler\Bison\Directive;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Compiler\Bison\Ast\BisonStartDeclaration;
use SqlFaker\Compiler\Bison\Directive\StartDirectiveReader;
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

#[CoversClass(StartDirectiveReader::class)]
#[UsesClass(BisonLexeme::class)]
#[UsesClass(BisonLexer::class)]
#[UsesClass(BisonLexer::class)]
#[UsesClass(BisonScannerChain::class)]
#[UsesClass(BisonStartDeclaration::class)]
#[UsesClass(BisonToken::class)]
#[UsesClass(BisonTokenStream::class)]
#[UsesClass(BisonTrivia::class)]
#[UsesClass(IdentifierScanner::class)]
#[UsesClass(NumberScanner::class)]
#[UsesClass(SourceCursor::class)]
#[UsesClass(ActionScanner::class)]
#[UsesClass(DirectiveScanner::class)]
#[UsesClass(QuotedLiteralScanner::class)]
#[UsesClass(TypeTagScanner::class)]
final class StartDirectiveReaderTest extends TestCase
{
    public function testHandlesClaimsOnlyTheStartDirective(): void
    {
        $reader = new StartDirectiveReader();

        self::assertTrue($reader->handles('%start'));
        self::assertFalse($reader->handles('%token'));
    }

    public function testReadTakesTheRuleName(): void
    {
        $declaration = (new StartDirectiveReader())->read(BisonTokenStream::over('statement'), '%start');

        self::assertInstanceOf(BisonStartDeclaration::class, $declaration);
        self::assertSame('statement', $declaration->symbol);
    }

    public function testReadDeclaresNothingWhenNoRuleNameFollows(): void
    {
        $stream = BisonTokenStream::over('42');

        self::assertNull((new StartDirectiveReader())->read($stream, '%start'));
    }

    public function testReadLeavesATokenItDidNotClaim(): void
    {
        $stream = BisonTokenStream::over('42');

        (new StartDirectiveReader())->read($stream, '%start');

        self::assertSame(42, $stream->next()->value);
    }
}
