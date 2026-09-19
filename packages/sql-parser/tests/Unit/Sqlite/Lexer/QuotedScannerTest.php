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
use SqlParser\Sqlite\Lexer\QuotedScanner;
use SqlParser\Sqlite\Lexer\Scan;

#[CoversClass(QuotedScanner::class)]
#[UsesClass(Cursor::class)]
#[UsesClass(Lexeme::class)]
#[UsesClass(LexicalException::class)]
#[UsesClass(KeywordTable::class)]
#[UsesClass(Scan::class)]
#[UsesClass(\SqlParser\Lexer\SourcePosition::class)]
#[UsesClass(\SqlParser\Lexer\SourceException::class)]
#[Small]
final class QuotedScannerTest extends TestCase
{
    public function testScan(): void
    {
        $scanner = new QuotedScanner();
        $scan = static fn (string $sql): Scan => new Scan(new Cursor($sql), new KeywordTable([]));

        self::assertSame('STRING', $scanner->scan($scan("'it''s'"))?->name);
        self::assertSame('ID', $scanner->scan($scan('"a""b"'))?->name);
        self::assertSame('ID', $scanner->scan($scan('`a`'))?->name);
        self::assertSame('[a b]', $scanner->scan($scan('[a b] c'))?->text);
        self::assertSame('BLOB', $scanner->scan($scan("x'ff'"))?->name);
        self::assertNull($scanner->scan($scan('abc')));
        self::assertNull($scanner->scan($scan('xyz')));
    }

    public function testScanRejectsAnUnterminatedString(): void
    {
        $this->expectException(LexicalException::class);

        (new QuotedScanner())->scan(new Scan(new Cursor("'open"), new KeywordTable([])));
    }

    public function testScanRejectsAnUnterminatedBracket(): void
    {
        $this->expectException(LexicalException::class);

        (new QuotedScanner())->scan(new Scan(new Cursor('[open'), new KeywordTable([])));
    }

    public function testScanRejectsAnOddBlob(): void
    {
        $this->expectException(LexicalException::class);

        (new QuotedScanner())->scan(new Scan(new Cursor("x'f'"), new KeywordTable([])));
    }
}
