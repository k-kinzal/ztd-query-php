<?php

declare(strict_types=1);

namespace Tests\Unit\Sqlite\Lexer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Cursor;
use SqlParser\Lexer\Lexeme;
use SqlParser\Lexer\LexicalException;
use SqlParser\Sqlite\Lexer\KeywordTable;
use SqlParser\Sqlite\Lexer\NumberScanner;
use SqlParser\Sqlite\Lexer\Scan;

#[CoversClass(NumberScanner::class)]
#[UsesClass(Cursor::class)]
#[UsesClass(Lexeme::class)]
#[UsesClass(LexicalException::class)]
#[UsesClass(KeywordTable::class)]
#[UsesClass(Scan::class)]
#[UsesClass(\SqlParser\Lexer\SourcePosition::class)]
#[UsesClass(\SqlParser\Lexer\SourceException::class)]
#[Small]
final class NumberScannerTest extends TestCase
{
    public function testScan(): void
    {
        $scanner = new NumberScanner();
        $scan = static fn (string $sql): Scan => new Scan(new Cursor($sql), new KeywordTable([]));

        self::assertSame('INTEGER', $scanner->scan($scan('42 '))?->name);
        self::assertSame('INTEGER', $scanner->scan($scan('0x1F'))?->name);
        self::assertSame('QNUMBER', $scanner->scan($scan('1_000'))?->name);
        self::assertSame('QNUMBER', $scanner->scan($scan('0xF_F'))?->name);
        self::assertSame('FLOAT', $scanner->scan($scan('1.5'))?->name);
        self::assertSame('FLOAT', $scanner->scan($scan('.5'))?->name);
        self::assertSame('FLOAT', $scanner->scan($scan('1e5'))?->name);
        self::assertSame('1.', $scanner->scan($scan('1. '))?->text);
        self::assertNull($scanner->scan($scan('.')));
        self::assertNull($scanner->scan($scan('abc')));
    }

    public function testScanRejectsTrailingJunk(): void
    {
        $this->expectException(LexicalException::class);

        (new NumberScanner())->scan(new Scan(new Cursor('12ab'), new KeywordTable([])));
    }
}
