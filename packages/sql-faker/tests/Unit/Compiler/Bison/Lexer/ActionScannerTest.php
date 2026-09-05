<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Compiler\Bison\Lexer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Compiler\Bison\Lexer\ActionScanner;
use SqlFaker\Compiler\Bison\Lexer\BisonLexeme;
use SqlFaker\Compiler\Bison\Lexer\BisonToken;
use SqlFaker\Compiler\Bison\Lexer\BisonTrivia;
use SqlFaker\Grammar\Lexical\SourceCursor;

#[CoversClass(ActionScanner::class)]
#[UsesClass(BisonLexeme::class)]
#[UsesClass(BisonToken::class)]
#[UsesClass(BisonTrivia::class)]
#[UsesClass(SourceCursor::class)]
final class ActionScannerTest extends TestCase
{
    public function testHandlesClaimsOnlyAnOpeningBrace(): void
    {
        $scanner = new ActionScanner();

        self::assertTrue($scanner->handles('{'));
        self::assertFalse($scanner->handles('}'));
    }

    public function testScanExcludesTheOuterBracesFromTheBody(): void
    {
        $cursor = new SourceCursor('{ $$ = $1; } rest');

        $token = (new ActionScanner())->scan($cursor);

        self::assertSame(BisonLexeme::Action, $token->type);
        self::assertSame(' $$ = $1; ', $token->value);
        self::assertSame(0, $token->offset);
        self::assertSame(' rest', $cursor->takeRest());
    }

    public function testScanReadsAnEmptyActionAsAnEmptyBody(): void
    {
        $cursor = new SourceCursor('{}');

        self::assertSame('', (new ActionScanner())->scan($cursor)->value);
    }

    public function testScanKeepsNestedBracesInTheBody(): void
    {
        $cursor = new SourceCursor('{ if (x) { y(); } } rest');

        self::assertSame(' if (x) { y(); } ', (new ActionScanner())->scan($cursor)->value);
        self::assertSame(' rest', $cursor->takeRest());
    }

    public function testScanIgnoresABraceInsideAString(): void
    {
        $cursor = new SourceCursor('{ printf("}"); } rest');

        self::assertSame(' printf("}"); ', (new ActionScanner())->scan($cursor)->value);
        self::assertSame(' rest', $cursor->takeRest());
    }

    public function testScanIgnoresABraceInsideACharacterLiteral(): void
    {
        $cursor = new SourceCursor("{ c = '}'; } rest");

        self::assertSame(" c = '}'; ", (new ActionScanner())->scan($cursor)->value);
        self::assertSame(' rest', $cursor->takeRest());
    }

    public function testScanIgnoresABraceInsideALineComment(): void
    {
        $cursor = new SourceCursor("{ // }\n x; } rest");

        self::assertSame(" // }\n x; ", (new ActionScanner())->scan($cursor)->value);
        self::assertSame(' rest', $cursor->takeRest());
    }

    public function testScanIgnoresABraceInsideABlockComment(): void
    {
        $cursor = new SourceCursor('{ /* } */ x; } rest');

        self::assertSame(' /* } */ x; ', (new ActionScanner())->scan($cursor)->value);
        self::assertSame(' rest', $cursor->takeRest());
    }

    public function testScanReadsALoneSlashAsBodyText(): void
    {
        $cursor = new SourceCursor('{ a / b } rest');

        self::assertSame(' a / b ', (new ActionScanner())->scan($cursor)->value);
    }

    public function testScanRunsAnUnclosedActionToTheEndOfTheFile(): void
    {
        $cursor = new SourceCursor('{ never closed');

        self::assertSame(' never closed', (new ActionScanner())->scan($cursor)->value);
        self::assertTrue($cursor->atEnd());
    }
}
