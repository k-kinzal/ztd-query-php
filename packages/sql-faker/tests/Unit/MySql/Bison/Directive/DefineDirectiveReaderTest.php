<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Bison\Directive;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\SourceCursor;
use SqlFaker\MySql\Bison\Ast\BisonDefineDeclaration;
use SqlFaker\MySql\Bison\Directive\BisonDeclarationBoundary;
use SqlFaker\MySql\Bison\Directive\DefineDirectiveReader;
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

#[CoversClass(DefineDirectiveReader::class)]
#[UsesClass(BisonDeclarationBoundary::class)]
#[UsesClass(BisonTokenType::class)]
#[UsesClass(BisonLexer::class)]
#[UsesClass(\SqlFaker\MySql\Bison\Lexer\BisonTokenScanner::class)]
#[UsesClass(BisonScannerChain::class)]
#[UsesClass(BisonToken::class)]
#[UsesClass(BisonTokenStream::class)]
#[UsesClass(BisonTrivia::class)]
#[UsesClass(SourceCursor::class)]
#[UsesClass(BisonDefineDeclaration::class)]
#[UsesClass(IdentifierScanner::class)]
#[UsesClass(NumberScanner::class)]
#[UsesClass(QuotedLiteralScanner::class)]
#[UsesClass(ActionScanner::class)]
#[UsesClass(DirectiveScanner::class)]
#[UsesClass(TypeTagScanner::class)]
final class DefineDirectiveReaderTest extends TestCase
{
    public function testHandlesClaimsOnlyTheDefineDirective(): void
    {
        $reader = new DefineDirectiveReader();

        self::assertTrue($reader->handles('%define'));
        self::assertFalse($reader->handles('%expect'));
    }

    public function testReadTakesANameWithoutAValueAsAFlag(): void
    {
        $declaration = (new DefineDirectiveReader())->read(BisonTokenStream::over('api.pure'), '%define');

        self::assertInstanceOf(BisonDefineDeclaration::class, $declaration);
        self::assertSame('api.pure', $declaration->name);
        self::assertNull($declaration->value);
    }

    public function testReadTakesAQuotedValue(): void
    {
        $declaration = (new DefineDirectiveReader())->read(BisonTokenStream::over('api.prefix "yy"'), '%define');

        self::assertInstanceOf(BisonDefineDeclaration::class, $declaration);
        self::assertSame('yy', $declaration->value);
    }

    public function testReadTakesANumericValue(): void
    {
        $declaration = (new DefineDirectiveReader())->read(BisonTokenStream::over('lr.keep 3'), '%define');

        self::assertInstanceOf(BisonDefineDeclaration::class, $declaration);
        self::assertSame('3', $declaration->value);
    }

    public function testReadStopsBeforeTheNextDirective(): void
    {
        $stream = BisonTokenStream::over('api.pure %token');

        $declaration = (new DefineDirectiveReader())->read($stream, '%define');

        self::assertInstanceOf(BisonDefineDeclaration::class, $declaration);
        self::assertNull($declaration->value);
        self::assertSame('%token', $stream->next()->value);
    }

    public function testReadDeclaresNothingWhenNoNameFollows(): void
    {
        self::assertNull((new DefineDirectiveReader())->read(BisonTokenStream::over('42'), '%define'));
    }
}
