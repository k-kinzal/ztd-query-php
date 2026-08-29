<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Bison\Directive;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Source\SourceCursor;
use SqlFaker\MySql\Bison\Ast\BisonParamDeclaration;
use SqlFaker\MySql\Bison\Directive\BisonDeclarationBoundary;
use SqlFaker\MySql\Bison\Directive\ParamDirectiveReader;
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

#[CoversClass(ParamDirectiveReader::class)]
#[UsesClass(BisonDeclarationBoundary::class)]
#[UsesClass(BisonLexeme::class)]
#[UsesClass(BisonLexer::class)]
#[UsesClass(BisonScannerChain::class)]
#[UsesClass(BisonToken::class)]
#[UsesClass(BisonTokenStream::class)]
#[UsesClass(BisonTrivia::class)]
#[UsesClass(SourceCursor::class)]
#[UsesClass(ActionScanner::class)]
#[UsesClass(BisonParamDeclaration::class)]
#[UsesClass(DirectiveScanner::class)]
#[UsesClass(IdentifierScanner::class)]
#[UsesClass(NumberScanner::class)]
#[UsesClass(QuotedLiteralScanner::class)]
#[UsesClass(TypeTagScanner::class)]
final class ParamDirectiveReaderTest extends TestCase
{
    public function testHandlesClaimsBothParameterDirectives(): void
    {
        $reader = new ParamDirectiveReader();

        self::assertTrue($reader->handles('%parse-param'));
        self::assertTrue($reader->handles('%lex-param'));
        self::assertFalse($reader->handles('%token'));
    }

    public function testReadTakesTheBracedArgumentAndTheDirectiveKind(): void
    {
        $declaration = (new ParamDirectiveReader())
            ->read(BisonTokenStream::over('{ THD *thd }'), '%parse-param');

        self::assertInstanceOf(BisonParamDeclaration::class, $declaration);
        self::assertSame('parse-param', $declaration->kind);
        self::assertSame(' THD *thd ', $declaration->code);
    }

    public function testReadDistinguishesTheScannerDirective(): void
    {
        $declaration = (new ParamDirectiveReader())
            ->read(BisonTokenStream::over('{ yyscan_t s }'), '%lex-param');

        self::assertInstanceOf(BisonParamDeclaration::class, $declaration);
        self::assertSame('lex-param', $declaration->kind);
    }

    public function testReadDeclaresNothingWhenNoBracedCodeFollows(): void
    {
        self::assertNull((new ParamDirectiveReader())->read(BisonTokenStream::over('thd'), '%parse-param'));
    }
}
