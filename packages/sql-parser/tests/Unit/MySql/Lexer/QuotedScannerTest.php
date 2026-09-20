<?php

declare(strict_types=1);

namespace Tests\Unit\MySql\Lexer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Cursor;
use SqlParser\Lexer\Lexeme;
use SqlParser\Lexer\LexicalException;
use SqlParser\MySql\Lexer\KeywordTable;
use SqlParser\MySql\Lexer\QuotedScanner;
use SqlParser\MySql\Lexer\Scan;
use SqlParser\MySql\MySqlVersion;
use SqlParser\MySql\SqlMode;

#[CoversClass(QuotedScanner::class)]
#[UsesClass(Cursor::class)]
#[UsesClass(Lexeme::class)]
#[UsesClass(LexicalException::class)]
#[UsesClass(KeywordTable::class)]
#[UsesClass(MySqlVersion::class)]
#[UsesClass(Scan::class)]
#[UsesClass(SqlMode::class)]
#[UsesClass(\SqlParser\Lexer\SourcePosition::class)]
#[UsesClass(\SqlParser\Resource\SqlVersion::class)]
#[UsesClass(\SqlParser\Resource\VersionRegistry::class)]
#[UsesClass(\SqlParser\Lexer\SourceException::class)]
#[Small]
final class QuotedScannerTest extends TestCase
{
    public function testScanReadsStringsAndIdentifiers(): void
    {
        $scanner = new QuotedScanner();
        $version = MySqlVersion::resolve();

        self::assertSame('TEXT_STRING', $scanner->scan(new Scan(new Cursor("'it''s\\'x' y"), new KeywordTable([], []), new SqlMode(), $version))?->name);
        self::assertSame('TEXT_STRING', $scanner->scan(new Scan(new Cursor('"x"'), new KeywordTable([], []), new SqlMode(), $version))?->name);
        self::assertSame('IDENT_QUOTED', $scanner->scan(new Scan(new Cursor('"x"'), new KeywordTable([], []), new SqlMode(ansiQuotes: true), $version))?->name);
        self::assertSame('`a``b`', $scanner->scan(new Scan(new Cursor('`a``b` c'), new KeywordTable([], []), new SqlMode(), $version))?->text);
        self::assertNull($scanner->scan(new Scan(new Cursor('abc'), new KeywordTable([], []), new SqlMode(), $version)));
    }

    public function testScanReadsPrefixedLiterals(): void
    {
        $scanner = new QuotedScanner();
        $version = MySqlVersion::resolve();

        self::assertSame('NCHAR_STRING', $scanner->scan(new Scan(new Cursor("N'x'"), new KeywordTable([], []), new SqlMode(), $version))?->name);
        self::assertSame('HEX_NUM', $scanner->scan(new Scan(new Cursor("x'ff'"), new KeywordTable([], []), new SqlMode(), $version))?->name);
        self::assertSame('BIN_NUM', $scanner->scan(new Scan(new Cursor("B'01'"), new KeywordTable([], []), new SqlMode(), $version))?->name);
        self::assertNull($scanner->scan(new Scan(new Cursor('xyz'), new KeywordTable([], []), new SqlMode(), $version)));
    }

    public function testScanRejectsAnOddHexadecimalLiteral(): void
    {
        $this->expectException(LexicalException::class);

        (new QuotedScanner())->scan(new Scan(new Cursor("x'f'"), new KeywordTable([], []), new SqlMode(), MySqlVersion::resolve()));
    }

    public function testString(): void
    {
        $scan = new Scan(new Cursor("'a\\'b'"), new KeywordTable([], []), new SqlMode(noBackslashEscapes: true), MySqlVersion::resolve());

        self::assertSame("'a\\'", (new QuotedScanner())->string($scan, 'TEXT_STRING', "'")->text);
    }

    public function testStringRejectsAnUnterminatedString(): void
    {
        $this->expectException(LexicalException::class);

        (new QuotedScanner())->string(new Scan(new Cursor("'open"), new KeywordTable([], []), new SqlMode(), MySqlVersion::resolve()), 'TEXT_STRING', "'");
    }

    public function testQuotedIdentifier(): void
    {
        $scan = new Scan(new Cursor('`x` y'), new KeywordTable([], []), new SqlMode(), MySqlVersion::resolve());

        self::assertSame('IDENT_QUOTED', (new QuotedScanner())->quotedIdentifier($scan, '`')->name);
    }

    public function testQuotedIdentifierRejectsAnUnterminatedIdentifier(): void
    {
        $this->expectException(LexicalException::class);

        (new QuotedScanner())->quotedIdentifier(new Scan(new Cursor('`open'), new KeywordTable([], []), new SqlMode(), MySqlVersion::resolve()), '`');
    }
}
