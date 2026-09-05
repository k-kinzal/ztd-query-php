<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Bison\Lexer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\GrammarParseException;
use SqlFaker\Grammar\SourceCursor;
use SqlFaker\MySql\Bison\Lexer\BisonLexeme;
use SqlFaker\MySql\Bison\Lexer\BisonToken;
use SqlFaker\MySql\Bison\Lexer\TypeTagScanner;

#[CoversClass(TypeTagScanner::class)]
#[UsesClass(BisonLexeme::class)]
#[UsesClass(BisonToken::class)]
#[UsesClass(GrammarParseException::class)]
#[UsesClass(SourceCursor::class)]
final class TypeTagScannerTest extends TestCase
{
    public function testHandlesClaimsOnlyAnOpeningBracket(): void
    {
        $scanner = new TypeTagScanner();

        self::assertTrue($scanner->handles('<'));
        self::assertFalse($scanner->handles('>'));
    }

    public function testScanExcludesTheBracketsFromTheTag(): void
    {
        $cursor = new SourceCursor('<num> rest');

        $token = (new TypeTagScanner())->scan($cursor);

        self::assertSame(BisonLexeme::TypeTag, $token->type);
        self::assertSame('num', $token->value);
        self::assertSame(0, $token->offset);
        self::assertSame(' rest', $cursor->takeRest());
    }

    public function testScanTrimsWhitespaceFromTheTag(): void
    {
        $cursor = new SourceCursor('<  num  >');

        self::assertSame('num', (new TypeTagScanner())->scan($cursor)->value);
    }

    public function testScanReadsAnEmptyTag(): void
    {
        $cursor = new SourceCursor('<>');

        self::assertSame('', (new TypeTagScanner())->scan($cursor)->value);
    }

    public function testScanReportsAnUnclosedTagRatherThanSwallowingTheFile(): void
    {
        $cursor = new SourceCursor('<num');

        $this->expectException(GrammarParseException::class);
        $this->expectExceptionMessage('Unterminated type tag starting at offset 0');

        (new TypeTagScanner())->scan($cursor);
    }
}
