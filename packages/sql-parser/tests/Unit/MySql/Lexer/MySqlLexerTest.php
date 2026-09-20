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
use SqlParser\MySql\Lexer\MySqlLexer;
use SqlParser\MySql\Lexer\NumberScanner;
use SqlParser\MySql\Lexer\OperatorScanner;
use SqlParser\MySql\Lexer\QuotedScanner;
use SqlParser\MySql\Lexer\Scan;
use SqlParser\MySql\Lexer\TriviaScanner;
use SqlParser\MySql\Lexer\VariableScanner;
use SqlParser\MySql\Lexer\WordScanner;
use SqlParser\MySql\MySqlVersion;
use SqlParser\MySql\SqlMode;

#[CoversClass(MySqlLexer::class)]
#[UsesClass(Charsets::class)]
#[UsesClass(Cursor::class)]
#[UsesClass(Lexeme::class)]
#[UsesClass(LexicalException::class)]
#[UsesClass(KeywordTable::class)]
#[UsesClass(MySqlVersion::class)]
#[UsesClass(NumberScanner::class)]
#[UsesClass(OperatorScanner::class)]
#[UsesClass(QuotedScanner::class)]
#[UsesClass(Scan::class)]
#[UsesClass(SqlMode::class)]
#[UsesClass(TriviaScanner::class)]
#[UsesClass(VariableScanner::class)]
#[UsesClass(WordScanner::class)]
#[UsesClass(\SqlParser\Lexer\SourcePosition::class)]
#[UsesClass(\SqlParser\Resource\SqlVersion::class)]
#[UsesClass(\SqlParser\Resource\VersionRegistry::class)]
#[UsesClass(\SqlParser\Lexer\SourceException::class)]
#[Small]
final class MySqlLexerTest extends TestCase
{
    public function testScanReadsAStatementIntoTerminals(): void
    {
        $version = MySqlVersion::resolve('mysql-8.4.7');
        $lexer = new MySqlLexer(KeywordTable::load($version->release->keywordPath), $version);
        $names = array_map(static fn (Lexeme $lexeme): string => $lexeme->name, $lexer->scan("SELECT t.select, @v := 1, @@session.sql_mode, u@localhost FROM db.t WHERE a <=> ? -- x\n"));

        self::assertSame(['SELECT_SYM', 'IDENT', '.', 'IDENT', ',', '@', 'LEX_HOSTNAME', 'SET_VAR', 'NUM', ',', '@', '@', 'SESSION_SYM', '.', 'IDENT', ',', 'IDENT', '@', 'LEX_HOSTNAME', 'FROM', 'IDENT', '.', 'IDENT', 'WHERE', 'IDENT', 'EQUAL_SYM', 'PARAM_MARKER', 'END_OF_INPUT'], $names);
    }

    public function testScanReadsAnEmptyHostNameAsTheServerDoes(): void
    {
        $version = MySqlVersion::resolve('mysql-8.4.7');
        $lexer = new MySqlLexer(KeywordTable::load($version->release->keywordPath), $version);
        $lexemes = $lexer->scan('SELECT @ x');

        self::assertSame(['SELECT_SYM', '@', 'LEX_HOSTNAME', 'IDENT', 'END_OF_INPUT'], array_map(static fn (Lexeme $lexeme): string => $lexeme->name, $lexemes));
        self::assertSame('', $lexemes[2]->text);
    }

    public function testScanRejectsAnUnreadableCharacter(): void
    {
        $version = MySqlVersion::resolve();

        $this->expectException(LexicalException::class);

        (new MySqlLexer(KeywordTable::load($version->release->keywordPath), $version))->scan('SELECT \\');
    }

    public function testNext(): void
    {
        $version = MySqlVersion::resolve();
        $lexer = new MySqlLexer(KeywordTable::load($version->release->keywordPath), $version);
        $scan = new Scan(new Cursor('x'), KeywordTable::load($version->release->keywordPath), new SqlMode(), $version);
        $scan->next = LexerState::IdentifierStart;

        self::assertSame('IDENT', $lexer->next($scan)->name);
        self::assertSame(LexerState::Start, $scan->next);
    }

    public function testMerged(): void
    {
        $modern = MySqlVersion::resolve('mysql-8.4.7');
        $legacy = MySqlVersion::resolve('mysql-5.7.44');
        $sql = 'WITH ROLLUP WITH CUBE WITH x';
        $lexemes = [new Lexeme('WITH', 'WITH', 0), new Lexeme('ROLLUP_SYM', 'ROLLUP', 5), new Lexeme('WITH', 'WITH', 12), new Lexeme('CUBE_SYM', 'CUBE', 17), new Lexeme('WITH', 'WITH', 22), new Lexeme('IDENT', 'x', 27)];
        $modernNames = array_map(static fn (Lexeme $lexeme): string => $lexeme->name, (new MySqlLexer(new KeywordTable([], []), $modern))->merged($lexemes, $sql));
        $legacyMerged = (new MySqlLexer(new KeywordTable([], []), $legacy))->merged($lexemes, $sql);

        self::assertSame(['WITH_ROLLUP_SYM', 'WITH', 'CUBE_SYM', 'WITH', 'IDENT'], $modernNames);
        self::assertSame(['WITH_ROLLUP_SYM', 'WITH_CUBE_SYM', 'WITH', 'IDENT'], array_map(static fn (Lexeme $lexeme): string => $lexeme->name, $legacyMerged));
        self::assertSame('WITH ROLLUP', $legacyMerged[0]->text);
    }
}
