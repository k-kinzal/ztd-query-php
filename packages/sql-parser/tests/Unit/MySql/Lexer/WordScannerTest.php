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
use SqlParser\MySql\Lexer\Charsets;
use SqlParser\MySql\Lexer\KeywordTable;
use SqlParser\MySql\Lexer\LexerState;
use SqlParser\MySql\Lexer\Scan;
use SqlParser\MySql\Lexer\WordScanner;
use SqlParser\MySql\MySqlVersion;
use SqlParser\MySql\SqlMode;

#[CoversClass(WordScanner::class)]
#[UsesClass(Charsets::class)]
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
final class WordScannerTest extends TestCase
{
    public function testScan(): void
    {
        $scanner = new WordScanner();
        $keywords = new KeywordTable(['SELECT' => 'SELECT_SYM'], ['COUNT' => 'COUNT_SYM']);
        $scan = static fn (string $sql, ?MySqlVersion $version = null): Scan => new Scan(new Cursor($sql), $keywords, new SqlMode(), $version ?? MySqlVersion::resolve());

        self::assertSame('SELECT_SYM', $scanner->scan($scan('select 1'))?->name);
        self::assertSame('IDENT', $scanner->scan($scan('count '))?->name);
        self::assertSame('COUNT_SYM', $scanner->scan($scan('count('))?->name);
        self::assertSame('UNDERSCORE_CHARSET', $scanner->scan($scan("_utf8mb4'x'"))?->name);
        self::assertSame('IDENT', $scanner->scan($scan('_unknown'))?->name);
        self::assertSame('DOLLAR_QUOTED_STRING_SYM', $scanner->scan($scan('$$text$$'))?->name);
        self::assertSame('IDENT', $scanner->scan($scan('$$text$$', MySqlVersion::resolve('mysql-8.0.44')))?->name);
        self::assertSame('IDENT', $scanner->scan($scan('$x'))?->name);
        self::assertNull($scanner->scan($scan('1a')));
        self::assertNull($scanner->scan($scan('(')));
    }

    public function testWordKeepsAKeywordBeforeADotAsAnIdentifier(): void
    {
        $scan = new Scan(new Cursor('select.x'), new KeywordTable(['SELECT' => 'SELECT_SYM'], []), new SqlMode(), MySqlVersion::resolve());
        $lexeme = (new WordScanner())->word($scan, 0);

        self::assertSame('IDENT', $lexeme->name);
        self::assertSame(LexerState::IdentifierSeparator, $scan->next);
    }

    public function testWordLooksUpFunctionsAcrossSpacesUnderIgnoreSpace(): void
    {
        $keywords = new KeywordTable([], ['COUNT' => 'COUNT_SYM']);

        self::assertSame('COUNT_SYM', (new WordScanner())->word(new Scan(new Cursor('count ('), $keywords, new SqlMode(ignoreSpace: true), MySqlVersion::resolve()), 0)->name);
        self::assertSame('IDENT', (new WordScanner())->word(new Scan(new Cursor('count ('), $keywords, new SqlMode(), MySqlVersion::resolve()), 0)->name);
    }

    public function testIdentifier(): void
    {
        $scan = new Scan(new Cursor('select.x'), new KeywordTable(['SELECT' => 'SELECT_SYM'], []), new SqlMode(), MySqlVersion::resolve());
        $lexeme = (new WordScanner())->identifier($scan, 0);

        self::assertSame('IDENT', $lexeme->name);
        self::assertSame('select', $lexeme->text);
        self::assertSame(LexerState::IdentifierSeparator, $scan->next);
    }

    public function testConsume(): void
    {
        $cursor = new Cursor('ab_1$é rest');

        self::assertSame('ab_1$é', (new WordScanner())->consume($cursor));
        self::assertSame(' ', $cursor->peek());
    }

    public function testIdentifierLexeme(): void
    {
        $scan = new Scan(new Cursor('猫'), new KeywordTable([], []), new SqlMode(), MySqlVersion::resolve());
        $scan->cursor->take(3);

        self::assertSame('IDENT_QUOTED', (new WordScanner())->identifierLexeme($scan, 0, '猫')->name);
        self::assertSame('IDENT', (new WordScanner())->identifierLexeme($scan, 0, 'cat')->name);
    }

    public function testDollarQuoted(): void
    {
        $scanner = new WordScanner();

        self::assertSame('$tag$ a $x$ b $tag$', $scanner->dollarQuoted(new Scan(new Cursor('$tag$ a $x$ b $tag$ c'), new KeywordTable([], []), new SqlMode(), MySqlVersion::resolve()))?->text);
        self::assertNull($scanner->dollarQuoted(new Scan(new Cursor('$abc'), new KeywordTable([], []), new SqlMode(), MySqlVersion::resolve())));
    }

    public function testDollarQuotedRejectsAnUnterminatedString(): void
    {
        $this->expectException(LexicalException::class);

        (new WordScanner())->dollarQuoted(new Scan(new Cursor('$$ open'), new KeywordTable([], []), new SqlMode(), MySqlVersion::resolve()));
    }
}
