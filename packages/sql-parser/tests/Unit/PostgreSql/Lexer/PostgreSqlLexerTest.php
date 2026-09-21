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
use SqlParser\PostgreSql\Lexer\LookaheadFilter;
use SqlParser\PostgreSql\Lexer\NumberScanner;
use SqlParser\PostgreSql\Lexer\OperatorScanner;
use SqlParser\PostgreSql\Lexer\PostgreSqlLexer;
use SqlParser\PostgreSql\Lexer\QuotedScanner;
use SqlParser\PostgreSql\Lexer\Scan;
use SqlParser\PostgreSql\Lexer\TriviaScanner;
use SqlParser\PostgreSql\Lexer\WordScanner;

#[CoversClass(PostgreSqlLexer::class)]
#[UsesClass(Cursor::class)]
#[UsesClass(Lexeme::class)]
#[UsesClass(LexicalException::class)]
#[UsesClass(KeywordTable::class)]
#[UsesClass(LookaheadFilter::class)]
#[UsesClass(NumberScanner::class)]
#[UsesClass(OperatorScanner::class)]
#[UsesClass(QuotedScanner::class)]
#[UsesClass(Scan::class)]
#[UsesClass(TriviaScanner::class)]
#[UsesClass(WordScanner::class)]
#[UsesClass(\SqlParser\Lexer\SourcePosition::class)]
#[UsesClass(\SqlParser\Lexer\SourceException::class)]
#[Small]
#[UsesClass(\SqlParser\Parser\AlternativeParser::class)]
#[UsesClass(\SqlParser\Parser\ParseBranch::class)]
#[UsesClass(\SqlParser\Table\AlternativeCodec::class)]
final class PostgreSqlLexerTest extends TestCase
{
    public function testScan(): void
    {
        $lexer = new PostgreSqlLexer(new KeywordTable(['SELECT' => 'SELECT', 'FROM' => 'FROM', 'NOT' => 'NOT', 'LIKE' => 'LIKE']));
        $names = array_map(static fn (Lexeme $lexeme): string => $lexeme->name, $lexer->scan("SELECT a::int, $1, 'x' FROM t -- c\n WHERE b NOT LIKE '%' AND c <@ d"));

        self::assertSame(['SELECT', 'IDENT', 'TYPECAST', 'IDENT', ',', 'PARAM', ',', 'SCONST', 'FROM', 'IDENT', 'IDENT', 'IDENT', 'NOT_LA', 'LIKE', 'SCONST', 'IDENT', 'IDENT', 'Op', 'IDENT'], $names);
        self::assertSame([], $lexer->scan('  /* only */ '));
    }

    public function testScanRejectsAnUnreadableCharacter(): void
    {
        $this->expectException(LexicalException::class);

        (new PostgreSqlLexer(new KeywordTable([])))->scan('SELECT {');
    }
}
