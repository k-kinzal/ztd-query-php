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
use SqlParser\MySql\Lexer\Scan;
use SqlParser\MySql\Lexer\VariableScanner;
use SqlParser\MySql\Lexer\WordScanner;
use SqlParser\MySql\MySqlVersion;
use SqlParser\MySql\SqlMode;

#[CoversClass(VariableScanner::class)]
#[UsesClass(Cursor::class)]
#[UsesClass(Lexeme::class)]
#[UsesClass(LexicalException::class)]
#[UsesClass(KeywordTable::class)]
#[UsesClass(MySqlVersion::class)]
#[UsesClass(Scan::class)]
#[UsesClass(SqlMode::class)]
#[UsesClass(WordScanner::class)]
#[UsesClass(\SqlParser\Lexer\SourcePosition::class)]
#[UsesClass(\SqlParser\Resource\SqlVersion::class)]
#[UsesClass(\SqlParser\Resource\VersionRegistry::class)]
#[UsesClass(\SqlParser\Lexer\SourceException::class)]
#[Small]
#[UsesClass(\SqlParser\Parser\AlternativeParser::class)]
#[UsesClass(\SqlParser\Parser\ParseBranch::class)]
#[UsesClass(\SqlParser\Table\AlternativeCodec::class)]
final class VariableScannerTest extends TestCase
{
    public function testAt(): void
    {
        $scanner = new VariableScanner();
        $scan = static fn (string $sql): Scan => new Scan(new Cursor($sql), new KeywordTable([], []), new SqlMode(), MySqlVersion::resolve());
        $host = $scan('@localhost');
        $system = $scan('@@x');
        $quoted = $scan("@'x'");

        self::assertSame('@', $scanner->at($host)->name);
        self::assertSame(LexerState::Hostname, $host->next);
        $scanner->at($system);
        self::assertSame(LexerState::SystemVariable, $system->next);
        $scanner->at($quoted);
        self::assertSame(LexerState::Start, $quoted->next);
    }

    public function testHostname(): void
    {
        $scanner = new VariableScanner();
        $named = new Scan(new Cursor('host.example$1 x'), new KeywordTable([], []), new SqlMode(), MySqlVersion::resolve());
        $empty = new Scan(new Cursor(' x'), new KeywordTable([], []), new SqlMode(), MySqlVersion::resolve());

        self::assertSame('host.example$1', $scanner->hostname($named)->text);
        self::assertSame(LexerState::Start, $named->next);
        self::assertSame('', $scanner->hostname($empty)->text);
        self::assertSame('LEX_HOSTNAME', $scanner->hostname($empty)->name);
    }

    public function testSystemVariable(): void
    {
        $scanner = new VariableScanner();
        $named = new Scan(new Cursor('@sql_mode'), new KeywordTable([], []), new SqlMode(), MySqlVersion::resolve());
        $quoted = new Scan(new Cursor('@`x`'), new KeywordTable([], []), new SqlMode(), MySqlVersion::resolve());

        self::assertSame('@', $scanner->systemVariable($named)->name);
        self::assertSame(LexerState::IdentifierOrKeyword, $named->next);
        $scanner->systemVariable($quoted);
        self::assertSame(LexerState::Start, $quoted->next);
    }

    public function testIdentifierOrKeyword(): void
    {
        $scanner = new VariableScanner();
        $keywords = new KeywordTable(['GLOBAL' => 'GLOBAL_SYM'], []);
        $qualified = new Scan(new Cursor('global.x'), $keywords, new SqlMode(), MySqlVersion::resolve());
        $plain = new Scan(new Cursor('sql_mode '), $keywords, new SqlMode(), MySqlVersion::resolve());

        self::assertSame('GLOBAL_SYM', $scanner->identifierOrKeyword($qualified, new WordScanner())->name);
        self::assertSame(LexerState::IdentifierSeparator, $qualified->next);
        self::assertSame('IDENT', $scanner->identifierOrKeyword($plain, new WordScanner())->name);
        self::assertSame(LexerState::Start, $plain->next);
    }

    public function testIdentifierOrKeywordRejectsAMissingName(): void
    {
        $this->expectException(LexicalException::class);

        (new VariableScanner())->identifierOrKeyword(new Scan(new Cursor(' '), new KeywordTable([], []), new SqlMode(), MySqlVersion::resolve()), new WordScanner());
    }
}
