<?php

declare(strict_types=1);

namespace Tests\Unit\Lexer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlParser\Lexer\Tokenizer;
use SqlParser\MySql\MySqlParser;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlParser\Sqlite\SqliteParser;

#[CoversClass(Tokenizer::class)]
#[UsesClass(\SqlParser\Grammar\SymbolTable::class)]
#[UsesClass(\SqlParser\Lexer\Cursor::class)]
#[UsesClass(\SqlParser\Lexer\Lexeme::class)]
#[UsesClass(\SqlParser\Lexer\LexicalException::class)]
#[UsesClass(\SqlParser\Lexer\SourceException::class)]
#[UsesClass(\SqlParser\Lexer\SourcePosition::class)]
#[UsesClass(\SqlParser\Lexer\TerminalIndex::class)]
#[UsesClass(Token::class)]
#[UsesClass(\SqlParser\MySql\Lexer\KeywordTable::class)]
#[UsesClass(\SqlParser\MySql\Lexer\MySqlLexer::class)]
#[UsesClass(\SqlParser\MySql\Lexer\NumberScanner::class)]
#[UsesClass(\SqlParser\MySql\Lexer\OperatorScanner::class)]
#[UsesClass(\SqlParser\MySql\Lexer\QuotedScanner::class)]
#[UsesClass(\SqlParser\MySql\Lexer\Scan::class)]
#[UsesClass(\SqlParser\MySql\Lexer\TriviaScanner::class)]
#[UsesClass(\SqlParser\MySql\Lexer\VariableScanner::class)]
#[UsesClass(\SqlParser\MySql\Lexer\WordScanner::class)]
#[UsesClass(MySqlParser::class)]
#[UsesClass(\SqlParser\MySql\MySqlVersion::class)]
#[UsesClass(\SqlParser\MySql\SqlMode::class)]
#[UsesClass(\SqlParser\Parser\LrParser::class)]
#[UsesClass(\SqlParser\Parser\Node::class)]
#[UsesClass(\SqlParser\Parser\SyntaxException::class)]
#[UsesClass(\SqlParser\PostgreSql\Lexer\KeywordTable::class)]
#[UsesClass(\SqlParser\PostgreSql\Lexer\LookaheadFilter::class)]
#[UsesClass(\SqlParser\PostgreSql\Lexer\NumberScanner::class)]
#[UsesClass(\SqlParser\PostgreSql\Lexer\OperatorScanner::class)]
#[UsesClass(\SqlParser\PostgreSql\Lexer\PostgreSqlLexer::class)]
#[UsesClass(\SqlParser\PostgreSql\Lexer\QuotedScanner::class)]
#[UsesClass(\SqlParser\PostgreSql\Lexer\Scan::class)]
#[UsesClass(\SqlParser\PostgreSql\Lexer\TriviaScanner::class)]
#[UsesClass(\SqlParser\PostgreSql\Lexer\WordScanner::class)]
#[UsesClass(PostgreSqlParser::class)]
#[UsesClass(\SqlParser\PostgreSql\PostgreSqlVersion::class)]
#[UsesClass(\SqlParser\Resource\SqlVersion::class)]
#[UsesClass(\SqlParser\Resource\VersionRegistry::class)]
#[UsesClass(\SqlParser\Sqlite\Lexer\KeywordTable::class)]
#[UsesClass(\SqlParser\Sqlite\Lexer\NumberScanner::class)]
#[UsesClass(\SqlParser\Sqlite\Lexer\OperatorScanner::class)]
#[UsesClass(\SqlParser\Sqlite\Lexer\QuotedScanner::class)]
#[UsesClass(\SqlParser\Sqlite\Lexer\Scan::class)]
#[UsesClass(\SqlParser\Sqlite\Lexer\SqliteLexer::class)]
#[UsesClass(\SqlParser\Sqlite\Lexer\TriviaScanner::class)]
#[UsesClass(\SqlParser\Sqlite\Lexer\VariableScanner::class)]
#[UsesClass(\SqlParser\Sqlite\Lexer\WordScanner::class)]
#[UsesClass(SqliteParser::class)]
#[UsesClass(\SqlParser\Sqlite\SqliteVersion::class)]
#[UsesClass(\SqlParser\Table\ActionCode::class)]
#[UsesClass(\SqlParser\Table\PackedRows::class)]
#[UsesClass(\SqlParser\Table\ParseTable::class)]
#[UsesClass(\SqlParser\Table\TableCodec::class)]
#[UsesClass(\SqlParser\Table\TableFile::class)]
#[UsesClass(\SqlParser\Table\TableRule::class)]
#[Small]
final class TokenizerTest extends TestCase
{
    #[DataProvider('providerTokenizers')]
    public function testTokenizeReadsTextIntoTerminalsEndingWithTheEndMarker(Tokenizer $tokenizer): void
    {
        $tokens = $tokenizer->tokenize('SELECT 1');
        $texts = array_values(array_filter(
            array_map(static fn (Token $token): string => $token->text, $tokens),
            static fn (string $text): bool => $text !== '',
        ));

        self::assertSame(['SELECT', '1'], $texts);
        self::assertSame('', $tokens[count($tokens) - 1]->text);
    }

    /**
     * @return array<string, array{Tokenizer}>
     */
    public static function providerTokenizers(): array
    {
        return [
            'MySQL' => [new MySqlParser()],
            'PostgreSQL' => [new PostgreSqlParser()],
            'SQLite' => [new SqliteParser()],
        ];
    }
}
