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
use SqlParser\PostgreSql\Lexer\OperatorScanner;
use SqlParser\PostgreSql\Lexer\Scan;

#[CoversClass(OperatorScanner::class)]
#[UsesClass(Cursor::class)]
#[UsesClass(Lexeme::class)]
#[UsesClass(LexicalException::class)]
#[UsesClass(KeywordTable::class)]
#[UsesClass(Scan::class)]
#[UsesClass(\SqlParser\Lexer\SourcePosition::class)]
#[UsesClass(\SqlParser\Lexer\SourceException::class)]
#[Small]
final class OperatorScannerTest extends TestCase
{
    public function testScan(): void
    {
        $scanner = new OperatorScanner();
        $scan = static fn (string $sql): Scan => new Scan(new Cursor($sql), new KeywordTable([]));

        self::assertSame('TYPECAST', $scanner->scan($scan('::int'))->name);
        self::assertSame('DOT_DOT', $scanner->scan($scan('..'))->name);
        self::assertSame('COLON_EQUALS', $scanner->scan($scan(':='))->name);
        self::assertSame('EQUALS_GREATER', $scanner->scan($scan('=>'))->name);
        self::assertSame('NOT_EQUALS', $scanner->scan($scan('<>'))->name);
        self::assertSame('NOT_EQUALS', $scanner->scan($scan('!='))->name);
        self::assertSame('LESS_EQUALS', $scanner->scan($scan('<='))->name);
        self::assertSame('+', $scanner->scan($scan('+ 1'))->name);
        self::assertSame('Op', $scanner->scan($scan('~'))->name);
        self::assertSame('<=>', $scanner->scan($scan('<=> 1'))->text);
        self::assertSame('<', $scanner->scan($scan('<-1'))->text);
        self::assertSame('@-', $scanner->scan($scan('@-1'))->text);
        self::assertSame('-', $scanner->scan($scan('--'))->text);
        self::assertSame('Op', $scanner->scan($scan('!=='))->name);
    }

    public function testScanRejectsAForeignCharacter(): void
    {
        $this->expectException(LexicalException::class);

        (new OperatorScanner())->scan(new Scan(new Cursor('{'), new KeywordTable([])));
    }

    public function testScanRejectsAnOverlongOperator(): void
    {
        $this->expectException(LexicalException::class);

        (new OperatorScanner())->scan(new Scan(new Cursor(str_repeat('~', 64)), new KeywordTable([])));
    }

    public function testOperatorRun(): void
    {
        $scanner = new OperatorScanner();

        self::assertSame('<@', $scanner->operatorRun('<@/* c */', 0));
        self::assertSame('+', $scanner->operatorRun('+--', 0));
        self::assertSame('*', $scanner->operatorRun('*+-', 0));
        self::assertSame('+', $scanner->operatorRun('++', 0));
        self::assertSame('?-', $scanner->operatorRun('?-', 0));
        self::assertNull($scanner->operatorRun('a+', 0));
    }
}
