<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Bison\Lexer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\SourceCursor;
use SqlFaker\MySql\Bison\Lexer\BisonLexeme;
use SqlFaker\MySql\Bison\Lexer\BisonToken;
use SqlFaker\MySql\Bison\Lexer\NumberScanner;

#[CoversClass(NumberScanner::class)]
#[UsesClass(BisonLexeme::class)]
#[UsesClass(BisonToken::class)]
#[UsesClass(SourceCursor::class)]
final class NumberScannerTest extends TestCase
{
    public function testHandlesClaimsOnlyADigit(): void
    {
        $scanner = new NumberScanner();

        self::assertTrue($scanner->handles('0'));
        self::assertTrue($scanner->handles('9'));
        self::assertFalse($scanner->handles('a'));
        self::assertFalse($scanner->handles('-'));
    }

    public function testScanCarriesTheValueAsAnInteger(): void
    {
        $cursor = new SourceCursor('123 rest');

        $token = (new NumberScanner())->scan($cursor);

        self::assertSame(BisonLexeme::Number, $token->type);
        self::assertSame(123, $token->value);
        self::assertSame(0, $token->offset);
        self::assertSame(' rest', $cursor->takeRest());
    }

    public function testScanStopsAtTheFirstNonDigit(): void
    {
        $cursor = new SourceCursor('12abc');

        self::assertSame(12, (new NumberScanner())->scan($cursor)->value);
        self::assertSame('abc', $cursor->takeRest());
    }

    public function testScanReadsLeadingZeroes(): void
    {
        $cursor = new SourceCursor('007');

        self::assertSame(7, (new NumberScanner())->scan($cursor)->value);
    }
}
