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
use SqlParser\Sqlite\Lexer\OperatorScanner;
use SqlParser\Sqlite\Lexer\QuotedScanner;
use SqlParser\Sqlite\Lexer\Scan;
use SqlParser\Sqlite\Lexer\SqliteLexer;
use SqlParser\Sqlite\Lexer\TriviaScanner;
use SqlParser\Sqlite\Lexer\VariableScanner;
use SqlParser\Sqlite\Lexer\WordScanner;

#[CoversClass(SqliteLexer::class)]
#[UsesClass(Cursor::class)]
#[UsesClass(Lexeme::class)]
#[UsesClass(LexicalException::class)]
#[UsesClass(KeywordTable::class)]
#[UsesClass(NumberScanner::class)]
#[UsesClass(OperatorScanner::class)]
#[UsesClass(QuotedScanner::class)]
#[UsesClass(Scan::class)]
#[UsesClass(TriviaScanner::class)]
#[UsesClass(VariableScanner::class)]
#[UsesClass(WordScanner::class)]
#[UsesClass(\SqlParser\Lexer\SourcePosition::class)]
#[UsesClass(\SqlParser\Lexer\SourceException::class)]
#[Small]
final class SqliteLexerTest extends TestCase
{
    public function testScanEndsWithAStatementTerminator(): void
    {
        $lexer = new SqliteLexer(new KeywordTable(['SELECT' => 'SELECT', 'FROM' => 'FROM', 'OVER' => 'OVER']));
        $names = array_map(static fn (Lexeme $lexeme): string => $lexeme->name, $lexer->scan("SELECT count(*) OVER w, x'ff', ?1 FROM t -- c"));

        self::assertSame(['SELECT', 'ID', 'LP', 'STAR', 'RP', 'OVER', 'ID', 'COMMA', 'BLOB', 'COMMA', 'VARIABLE', 'FROM', 'ID', 'SEMI'], $names);
        self::assertSame(['SELECT', 'SEMI'], array_map(static fn (Lexeme $lexeme): string => $lexeme->name, $lexer->scan('SELECT;')));
        self::assertSame(['SEMI'], array_map(static fn (Lexeme $lexeme): string => $lexeme->name, $lexer->scan('')));
    }

    public function testScanRejectsAnUnreadableCharacter(): void
    {
        $this->expectException(LexicalException::class);

        (new SqliteLexer(new KeywordTable([])))->scan('SELECT ^');
    }
}
