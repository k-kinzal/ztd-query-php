<?php

declare(strict_types=1);

namespace Tests\Unit\MySql\Lexer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Cursor;
use SqlParser\Lexer\Lexeme;
use SqlParser\MySql\Lexer\KeywordTable;
use SqlParser\MySql\Lexer\LexerState;
use SqlParser\MySql\Lexer\NumberScanner;
use SqlParser\MySql\Lexer\Scan;
use SqlParser\MySql\Lexer\WordScanner;
use SqlParser\MySql\MySqlVersion;
use SqlParser\MySql\SqlMode;

#[CoversClass(NumberScanner::class)]
#[UsesClass(Cursor::class)]
#[UsesClass(Lexeme::class)]
#[UsesClass(KeywordTable::class)]
#[UsesClass(MySqlVersion::class)]
#[UsesClass(Scan::class)]
#[UsesClass(SqlMode::class)]
#[UsesClass(WordScanner::class)]
#[UsesClass(\SqlParser\Resource\SqlVersion::class)]
#[UsesClass(\SqlParser\Resource\VersionRegistry::class)]
#[Small]
final class NumberScannerTest extends TestCase
{
    public function testScan(): void
    {
        $scanner = new NumberScanner();
        $words = new WordScanner();
        $version = MySqlVersion::resolve();
        $scan = static fn (string $sql): Scan => new Scan(new Cursor($sql), new KeywordTable([], []), new SqlMode(), $version);

        self::assertSame('NUM', $scanner->scan($scan('42 '), $words)?->name);
        self::assertSame('HEX_NUM', $scanner->scan($scan('0x1F'), $words)?->name);
        self::assertSame('BIN_NUM', $scanner->scan($scan('0b01 '), $words)?->name);
        self::assertSame('IDENT', $scanner->scan($scan('0xZZ'), $words)?->name);
        self::assertSame('DECIMAL_NUM', $scanner->scan($scan('1.5'), $words)?->name);
        self::assertSame('DECIMAL_NUM', $scanner->scan($scan('.5'), $words)?->name);
        self::assertSame('FLOAT_NUM', $scanner->scan($scan('1e5'), $words)?->name);
        self::assertSame('FLOAT_NUM', $scanner->scan($scan('1.5E-3'), $words)?->name);
        self::assertSame('IDENT', $scanner->scan($scan('1e'), $words)?->name);
        self::assertSame('IDENT', $scanner->scan($scan('1abc'), $words)?->name);
        self::assertNull($scanner->scan($scan('abc'), $words));
    }

    public function testScanNotesAQualifiedIdentifierThatStartsWithADigit(): void
    {
        $scan = new Scan(new Cursor('1a.b'), new KeywordTable([], []), new SqlMode(), MySqlVersion::resolve());

        self::assertSame('1a', (new NumberScanner())->scan($scan, new WordScanner())?->text);
        self::assertSame(LexerState::IdentifierSeparator, $scan->next);
    }

    public function testFraction(): void
    {
        $scan = new Scan(new Cursor('.25e2x'), new KeywordTable([], []), new SqlMode(), MySqlVersion::resolve());
        $lexeme = (new NumberScanner())->fraction($scan, 0);

        self::assertSame('FLOAT_NUM', $lexeme->name);
        self::assertSame('.25e2', $lexeme->text);
    }

    public function testIntegerTerminal(): void
    {
        self::assertSame('NUM', NumberScanner::integerTerminal('0002147483647'));
        self::assertSame('LONG_NUM', NumberScanner::integerTerminal('2147483648'));
        self::assertSame('LONG_NUM', NumberScanner::integerTerminal('9223372036854775807'));
        self::assertSame('ULONGLONG_NUM', NumberScanner::integerTerminal('9223372036854775808'));
        self::assertSame('ULONGLONG_NUM', NumberScanner::integerTerminal('18446744073709551615'));
        self::assertSame('DECIMAL_NUM', NumberScanner::integerTerminal('18446744073709551616'));
        self::assertSame('NUM', NumberScanner::integerTerminal('000'));
    }
}
