<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Compiler\Bison\Lexer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Compiler\Bison\Lexer\BisonLexeme;
use SqlFaker\Compiler\Bison\Lexer\BisonToken;
use SqlFaker\Compiler\Bison\Lexer\IdentifierScanner;
use SqlFaker\Grammar\Lexical\SourceCursor;

#[CoversClass(IdentifierScanner::class)]
#[UsesClass(BisonLexeme::class)]
#[UsesClass(BisonToken::class)]
#[UsesClass(SourceCursor::class)]
final class IdentifierScannerTest extends TestCase
{
    public function testHandlesClaimsALetterOrUnderscore(): void
    {
        $scanner = new IdentifierScanner();

        self::assertTrue($scanner->handles('a'));
        self::assertTrue($scanner->handles('Z'));
        self::assertTrue($scanner->handles('_'));
    }

    public function testHandlesRejectsADigit(): void
    {
        self::assertFalse((new IdentifierScanner())->handles('1'));
    }

    public function testScanCarriesTheNameSpelling(): void
    {
        $cursor = new SourceCursor('select_stmt rest');

        $token = (new IdentifierScanner())->scan($cursor);

        self::assertSame(BisonLexeme::Identifier, $token->type);
        self::assertSame('select_stmt', $token->value);
        self::assertSame(0, $token->offset);
        self::assertSame(' rest', $cursor->takeRest());
    }

    public function testScanAllowsDigitsAndDotsAfterTheFirstCharacter(): void
    {
        $cursor = new SourceCursor('api.pure2-tail');

        self::assertSame('api.pure2', (new IdentifierScanner())->scan($cursor)->value);
        self::assertSame('-tail', $cursor->takeRest());
    }
}
