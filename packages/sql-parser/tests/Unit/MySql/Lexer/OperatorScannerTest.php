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
use SqlParser\MySql\Lexer\LexerState;
use SqlParser\MySql\Lexer\OperatorScanner;
use SqlParser\MySql\Lexer\Scan;
use SqlParser\MySql\MySqlVersion;
use SqlParser\MySql\SqlMode;

#[CoversClass(OperatorScanner::class)]
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
final class OperatorScannerTest extends TestCase
{
    public function testScan(): void
    {
        $scanner = new OperatorScanner();
        $keywords = new KeywordTable(['<=>' => 'EQUAL_SYM', '<=' => 'LE', '<' => 'LT', '!=' => 'NE', '&&' => 'AND_AND_SYM', '||' => 'OR_OR_SYM', '=' => 'EQ'], []);
        $scan = static fn (string $sql): Scan => new Scan(new Cursor($sql), $keywords, new SqlMode(), MySqlVersion::resolve());

        self::assertSame('EQUAL_SYM', $scanner->scan($scan('<=> 1'))->name);
        self::assertSame('LE', $scanner->scan($scan('<= 1'))->name);
        self::assertSame('LT', $scanner->scan($scan('< 1'))->name);
        self::assertSame('OR2_SYM', $scanner->scan($scan('|| 1'))->name);
        self::assertSame('|', $scanner->scan($scan('| 1'))->name);
        self::assertSame('SET_VAR', $scanner->scan($scan(':= 1'))->name);
        self::assertSame(':', $scanner->scan($scan(': 1'))->name);
        self::assertSame('PARAM_MARKER', $scanner->scan($scan('? '))->name);
        self::assertSame('JSON_UNQUOTED_SEPARATOR_SYM', $scanner->scan($scan("->>'$'"))->name);
        self::assertSame('(', $scanner->scan($scan('('))->name);
        self::assertSame('!', $scanner->scan($scan('!x'))->name);
    }

    public function testScanRejectsAParameterMarkerGluedToAName(): void
    {
        $this->expectException(LexicalException::class);

        (new OperatorScanner())->scan(new Scan(new Cursor('?x'), new KeywordTable([], []), new SqlMode(), MySqlVersion::resolve()));
    }

    public function testScanRejectsAForeignCharacter(): void
    {
        $this->expectException(LexicalException::class);

        (new OperatorScanner())->scan(new Scan(new Cursor('\\'), new KeywordTable([], []), new SqlMode(), MySqlVersion::resolve()));
    }

    public function testMultiCharacter(): void
    {
        $scanner = new OperatorScanner();
        $keywords = new KeywordTable(['<<' => 'SHIFT_LEFT'], []);
        $modern = new Scan(new Cursor(''), $keywords, new SqlMode(), MySqlVersion::resolve('mysql-8.4.7'));
        $legacy = new Scan(new Cursor(''), $keywords, new SqlMode(), MySqlVersion::resolve('mysql-5.6.51'));

        self::assertSame('SET_VAR', $scanner->multiCharacter($modern, ':='));
        self::assertSame('JSON_SEPARATOR_SYM', $scanner->multiCharacter($modern, '->'));
        self::assertNull($scanner->multiCharacter($legacy, '->'));
        self::assertSame('SHIFT_LEFT', $scanner->multiCharacter($modern, '<<'));
        self::assertNull($scanner->multiCharacter($modern, '<-'));
        self::assertNull($scanner->multiCharacter($modern, 'ab'));
    }

    public function testSeparator(): void
    {
        $scanner = new OperatorScanner();
        $qualified = new Scan(new Cursor('.x'), new KeywordTable([], []), new SqlMode(), MySqlVersion::resolve());
        $bare = new Scan(new Cursor('. x'), new KeywordTable([], []), new SqlMode(), MySqlVersion::resolve());

        self::assertSame('.', $scanner->separator($qualified)->name);
        self::assertSame(LexerState::IdentifierStart, $qualified->next);
        self::assertSame(LexerState::Start, $bare->next === LexerState::Start ? $scanner->separator($bare)->name === '.' ? LexerState::Start : null : null);
        self::assertSame(LexerState::Start, $bare->next);
    }
}
