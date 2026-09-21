<?php

declare(strict_types=1);

namespace Tests\Unit\PostgreSql\Lexer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Cursor;
use SqlParser\Lexer\Lexeme;
use SqlParser\Lexer\LexicalException;
use SqlParser\PostgreSql\Lexer\KeywordTable;
use SqlParser\PostgreSql\Lexer\NumberScanner;
use SqlParser\PostgreSql\Lexer\Scan;

#[CoversClass(NumberScanner::class)]
#[UsesClass(Cursor::class)]
#[UsesClass(Lexeme::class)]
#[UsesClass(LexicalException::class)]
#[UsesClass(KeywordTable::class)]
#[UsesClass(Scan::class)]
#[UsesClass(\SqlParser\Lexer\SourcePosition::class)]
#[UsesClass(\SqlParser\Lexer\SourceException::class)]
#[Small]
#[UsesClass(\SqlParser\Parser\AlternativeParser::class)]
#[UsesClass(\SqlParser\Parser\ParseBranch::class)]
#[UsesClass(\SqlParser\Table\AlternativeCodec::class)]
final class NumberScannerTest extends TestCase
{
    public function testScan(): void
    {
        $scanner = new NumberScanner();
        $scan = static fn (string $sql): Scan => new Scan(new Cursor($sql), new KeywordTable([]));

        self::assertSame('ICONST', $scanner->scan($scan('42 '))?->name);
        self::assertSame('FCONST', $scanner->scan($scan('2147483648'))?->name);
        self::assertSame('ICONST', $scanner->scan($scan('1_000'))?->name);
        self::assertSame('ICONST', $scanner->scan($scan('0x1F'))?->name);
        self::assertSame('FCONST', $scanner->scan($scan('0o777_7777_7777'))?->name);
        self::assertSame('FCONST', $scanner->scan($scan('1.5'))?->name);
        self::assertSame('1.', $scanner->scan($scan('1. '))?->text);
        self::assertSame('.5', $scanner->scan($scan('.5'))?->text);
        self::assertSame('FCONST', $scanner->scan($scan('1e5'))?->name);
        self::assertSame('1', $scanner->scan($scan('1..2'))?->text);
        self::assertSame('PARAM', $scanner->scan($scan('$12 '))?->name);
        self::assertNull($scanner->scan($scan('abc')));
        self::assertNull($scanner->scan($scan('.')));
    }

    public function testScanRejectsTrailingJunk(): void
    {
        $this->expectException(LexicalException::class);

        (new NumberScanner())->scan(new Scan(new Cursor('123abc'), new KeywordTable([])));
    }

    public function testScanRejectsAnIncompleteExponent(): void
    {
        $this->expectException(LexicalException::class);

        (new NumberScanner())->scan(new Scan(new Cursor('1e+'), new KeywordTable([])));
    }

    public function testScanRejectsAnEmptyHexadecimalLiteral(): void
    {
        $this->expectException(LexicalException::class);

        (new NumberScanner())->scan(new Scan(new Cursor('0x_'), new KeywordTable([])));
    }

    public function testRejectJunk(): void
    {
        $scanner = new NumberScanner();
        $scanner->rejectJunk(new Scan(new Cursor(' '), new KeywordTable([])), 0);

        $this->expectException(LexicalException::class);

        $scanner->rejectJunk(new Scan(new Cursor('x'), new KeywordTable([])), 0);
    }

    public function testFitsInt32(): void
    {
        self::assertTrue(NumberScanner::fitsInt32('2147483647'));
        self::assertFalse(NumberScanner::fitsInt32('2147483648'));
        self::assertTrue(NumberScanner::fitsInt32('0x7fff_ffff'));
        self::assertFalse(NumberScanner::fitsInt32('0x80000000'));
        self::assertTrue(NumberScanner::fitsInt32('0o17777777777'));
        self::assertFalse(NumberScanner::fitsInt32('0o20000000000'));
        self::assertTrue(NumberScanner::fitsInt32('0b' . str_repeat('1', 31)));
        self::assertFalse(NumberScanner::fitsInt32('0b1' . str_repeat('0', 31)));
        self::assertTrue(NumberScanner::fitsInt32('0000'));
    }
}
