<?php

declare(strict_types=1);

namespace Tests\Unit\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlParser\Lexer\LexicalException;
use SqlParser\Lexer\Token;
use SqlParser\Parser\SyntaxException;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlParser\PostgreSql\PostgreSqlVersion;

#[CoversClass(PostgreSqlParser::class)]
#[UsesClass(PostgreSqlVersion::class)]
#[UsesClass(SyntaxException::class)]
#[UsesClass(LexicalException::class)]
#[UsesClass(Token::class)]
#[UsesClass(\SqlParser\Grammar\SymbolTable::class)]
#[UsesClass(\SqlParser\Lexer\Cursor::class)]
#[UsesClass(\SqlParser\Lexer\Lexeme::class)]
#[UsesClass(\SqlParser\Lexer\SourcePosition::class)]
#[UsesClass(\SqlParser\Lexer\TerminalIndex::class)]
#[UsesClass(\SqlParser\Parser\LrParser::class)]
#[UsesClass(\SqlParser\Parser\Node::class)]
#[UsesClass(\SqlParser\PostgreSql\Lexer\KeywordTable::class)]
#[UsesClass(\SqlParser\PostgreSql\Lexer\LookaheadFilter::class)]
#[UsesClass(\SqlParser\PostgreSql\Lexer\NumberScanner::class)]
#[UsesClass(\SqlParser\PostgreSql\Lexer\OperatorScanner::class)]
#[UsesClass(\SqlParser\PostgreSql\Lexer\PostgreSqlLexer::class)]
#[UsesClass(\SqlParser\PostgreSql\Lexer\QuotedScanner::class)]
#[UsesClass(\SqlParser\PostgreSql\Lexer\Scan::class)]
#[UsesClass(\SqlParser\PostgreSql\Lexer\TriviaScanner::class)]
#[UsesClass(\SqlParser\PostgreSql\Lexer\WordScanner::class)]
#[UsesClass(\SqlParser\Resource\SqlVersion::class)]
#[UsesClass(\SqlParser\Resource\VersionRegistry::class)]
#[UsesClass(\SqlParser\Table\ActionCode::class)]
#[UsesClass(\SqlParser\Table\PackedRows::class)]
#[UsesClass(\SqlParser\Table\ParseTable::class)]
#[UsesClass(\SqlParser\Table\TableCodec::class)]
#[UsesClass(\SqlParser\Table\TableFile::class)]
#[UsesClass(\SqlParser\Table\TableRule::class)]
#[UsesClass(\SqlParser\Lexer\SourceException::class)]
#[Small]
final class PostgreSqlParserTest extends TestCase
{
    public function testVersion(): void
    {
        self::assertSame('pg-17.2', (new PostgreSqlParser())->version());
    }

    public function testVersionRejectsAnUnknownRelease(): void
    {
        $this->expectException(RuntimeException::class);

        new PostgreSqlParser('pg-9.6');
    }

    public function testTokenize(): void
    {
        $tokens = (new PostgreSqlParser())->tokenize('SELECT "a" FROM t WHERE b IS NOT NULL AND c NOT LIKE $1');

        self::assertSame(['SELECT', 'IDENT', 'FROM', 'IDENT', 'WHERE', 'IDENT', 'IS', 'NOT', 'NULL_P', 'AND', 'IDENT', 'NOT_LA', 'LIKE', 'PARAM', '$end'], array_map(static fn (Token $token): string => $token->name, $tokens));
    }

    public function testTokenizeRejectsAnUnterminatedString(): void
    {
        $this->expectException(LexicalException::class);

        (new PostgreSqlParser())->tokenize("SELECT 'abc");
    }

    public function testParse(): void
    {
        $sql = 'SELECT id FROM users WHERE id = $1; INSERT INTO t (a) VALUES (1) RETURNING a';
        $tree = (new PostgreSqlParser())->parse($sql);

        self::assertSame('parse_toplevel', $tree->name);
        self::assertCount(2, array_filter($tree->find('stmt'), static fn ($node): bool => !$node->isEmpty()));
        self::assertSame('SELECT id FROM users WHERE id = $1', $tree->find('SelectStmt')[0]->text($sql));
    }

    public function testParseAcceptsAnEmptyInput(): void
    {
        self::assertSame('parse_toplevel', (new PostgreSqlParser())->parse('')->name);
    }

    public function testParseRejectsAChainedComparison(): void
    {
        $this->expectException(SyntaxException::class);

        (new PostgreSqlParser())->parse('SELECT 1 = 2 = 3');
    }

    public function testVersions(): void
    {
        self::assertSame(['pg-17.2'], PostgreSqlParser::versions());
    }
}
