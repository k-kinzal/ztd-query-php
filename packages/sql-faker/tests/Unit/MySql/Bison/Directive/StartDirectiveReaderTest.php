<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Bison\Directive;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\SourceCursor;
use SqlFaker\MySql\Bison\Ast\BisonStartDeclaration;
use SqlFaker\MySql\Bison\Directive\StartDirectiveReader;
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

#[CoversClass(StartDirectiveReader::class)]
#[UsesClass(BisonTokenType::class)]
#[UsesClass(BisonLexer::class)]
#[UsesClass(\SqlFaker\MySql\Bison\Lexer\BisonTokenScanner::class)]
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
