<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Source\Bison\Directive;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Source\Bison\Ast\BisonParamDeclaration;
use SqlFaker\Grammar\Source\Bison\Directive\BisonDeclarationBoundary;
use SqlFaker\Grammar\Source\Bison\Directive\ParamDirectiveReader;
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
