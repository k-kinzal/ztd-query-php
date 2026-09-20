<?php

declare(strict_types=1);

namespace Tests\Unit\Sqlite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlParser\Lexer\LexicalException;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlParser\Parser\SyntaxException;
use SqlParser\Sqlite\SqliteParser;
use SqlParser\Sqlite\SqliteVersion;

#[CoversClass(SqliteParser::class)]
#[UsesClass(SqliteVersion::class)]
#[UsesClass(SyntaxException::class)]
#[UsesClass(LexicalException::class)]
#[UsesClass(Token::class)]
#[UsesClass(\SqlParser\Grammar\SymbolTable::class)]
#[UsesClass(\SqlParser\Lexer\Cursor::class)]
#[UsesClass(\SqlParser\Lexer\Lexeme::class)]
#[UsesClass(\SqlParser\Lexer\SourcePosition::class)]
#[UsesClass(\SqlParser\Lexer\TerminalIndex::class)]
#[UsesClass(\SqlParser\Parser\LrParser::class)]
#[UsesClass(Node::class)]
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
#[UsesClass(\SqlParser\Table\ActionCode::class)]
#[UsesClass(\SqlParser\Table\PackedRows::class)]
#[UsesClass(\SqlParser\Table\ParseTable::class)]
#[UsesClass(\SqlParser\Table\TableCodec::class)]
#[UsesClass(\SqlParser\Table\TableFile::class)]
#[UsesClass(\SqlParser\Table\TableRule::class)]
#[UsesClass(\SqlParser\Lexer\SourceException::class)]
#[UsesClass(\SqlParser\Parser\Printer::class)]
#[UsesClass(\SqlParser\Parser\Separator::class)]
#[UsesClass(\SqlParser\Parser\Spacing::class)]
#[Small]
final class SqliteParserTest extends TestCase
{
    public function testVersion(): void
    {
        self::assertSame('sqlite-3.47.2', (new SqliteParser())->version());
    }

    public function testVersionRejectsAnUnknownRelease(): void
    {
        $this->expectException(RuntimeException::class);

        new SqliteParser('sqlite-2.8.17');
    }

    public function testTokenize(): void
    {
        $tokens = (new SqliteParser())->tokenize('SELECT abort, x FROM t WHERE y = :v');

        self::assertSame(['SELECT', 'ABORT', 'COMMA', 'ID', 'FROM', 'ID', 'WHERE', 'ID', 'EQ', 'VARIABLE', 'SEMI', '$end'], array_map(static fn (Token $token): string => $token->name, $tokens));
        self::assertSame('', $tokens[10]->text);
    }

    public function testTokenizeRejectsAnUnterminatedString(): void
    {
        $this->expectException(LexicalException::class);

        (new SqliteParser())->tokenize("SELECT 'abc");
    }

    public function testParse(): void
    {
        $sql = 'SELECT id FROM users WHERE id = ?; DELETE FROM t';
        $tree = (new SqliteParser())->parse($sql);

        self::assertSame('input', $tree->name);
        self::assertCount(2, $tree->find('cmd'));
        self::assertSame('SELECT id FROM users WHERE id = ?', $tree->find('select')[0]->text($sql));
    }

    public function testParseLetsKeywordsFallBackToIdentifiers(): void
    {
        $tree = (new SqliteParser())->parse('SELECT abort, window, over FROM temp');

        self::assertCount(1, $tree->find('select'));
    }

    public function testParseAcceptsAnEmptyInput(): void
    {
        self::assertSame('input', (new SqliteParser())->parse('')->name);
    }

    public function testParseRejectsAStatementThatEndsEarly(): void
    {
        $this->expectException(SyntaxException::class);

        (new SqliteParser())->parse('SELECT 1 FROM');
    }

    public function testVersions(): void
    {
        self::assertSame(['sqlite-3.47.2'], SqliteParser::versions());
    }

    public function testRenderWritesATreeBackOutAsText(): void
    {
        $parser = new SqliteParser();

        self::assertSame('SELECT id, name FROM users', $parser->render($parser->parse('SELECT  id,   name FROM users')));
    }

    public function testRenderWritesATreeOfBuiltTokensSoItReadsBack(): void
    {
        $parser = new SqliteParser();
        $detach = static function (Node|Token $branch) use (&$detach): Node|Token {
            return $branch instanceof Token
                ? $branch->detached()
                /** @var callable(Node|Token): (Node|Token) $detach */
                : new Node($branch->name, $branch->ordinal, array_map($detach, $branch->children));
        };
        $tree = $parser->parse('SELECT  id,   name FROM users');
        /** @var Node|Token $built */
        $built = $detach($tree);

        self::assertSame(
            array_map(static fn (Token $token): string => $token->name, $tree->tokens()),
            array_map(static fn (Token $token): string => $token->name, $parser->parse($parser->render($built))->tokens()),
        );
    }
}
