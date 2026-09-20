<?php

declare(strict_types=1);

namespace Tests\Unit\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlParser\Lexer\LexicalException;
use SqlParser\Lexer\Token;
use SqlParser\MySql\MySqlParser;
use SqlParser\MySql\MySqlVersion;
use SqlParser\MySql\SqlMode;
use SqlParser\Parser\SyntaxException;

#[CoversClass(MySqlParser::class)]
#[UsesClass(MySqlVersion::class)]
#[UsesClass(SqlMode::class)]
#[UsesClass(SyntaxException::class)]
#[UsesClass(LexicalException::class)]
#[UsesClass(Token::class)]
#[UsesClass(\SqlParser\Grammar\SymbolTable::class)]
#[UsesClass(\SqlParser\Lexer\Cursor::class)]
#[UsesClass(\SqlParser\Lexer\Lexeme::class)]
#[UsesClass(\SqlParser\Lexer\SourcePosition::class)]
#[UsesClass(\SqlParser\Lexer\TerminalIndex::class)]
#[UsesClass(\SqlParser\MySql\Lexer\KeywordTable::class)]
#[UsesClass(\SqlParser\MySql\Lexer\MySqlLexer::class)]
#[UsesClass(\SqlParser\MySql\Lexer\NumberScanner::class)]
#[UsesClass(\SqlParser\MySql\Lexer\OperatorScanner::class)]
#[UsesClass(\SqlParser\MySql\Lexer\QuotedScanner::class)]
#[UsesClass(\SqlParser\MySql\Lexer\Scan::class)]
#[UsesClass(\SqlParser\MySql\Lexer\TriviaScanner::class)]
#[UsesClass(\SqlParser\MySql\Lexer\VariableScanner::class)]
#[UsesClass(\SqlParser\MySql\Lexer\WordScanner::class)]
#[UsesClass(\SqlParser\Parser\LrParser::class)]
#[UsesClass(\SqlParser\Parser\Node::class)]
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
final class MySqlParserTest extends TestCase
{
    public function testVersion(): void
    {
        self::assertSame('mysql-8.4.7', (new MySqlParser())->version());
        self::assertSame('mysql-5.7.44', (new MySqlParser('mysql-5.7.44'))->version());
    }

    public function testVersionRejectsAnUnknownRelease(): void
    {
        $this->expectException(RuntimeException::class);

        new MySqlParser('mysql-4.1.0');
    }

    public function testTokenize(): void
    {
        $tokens = (new MySqlParser())->tokenize('SELECT `a` FROM t');

        self::assertSame(['SELECT_SYM', 'IDENT_QUOTED', 'FROM', 'IDENT', 'END_OF_INPUT', '$end'], array_map(static fn (Token $token): string => $token->name, $tokens));
        self::assertSame('`a`', $tokens[1]->text);
    }

    public function testTokenizeHonoursTheMode(): void
    {
        self::assertSame('IDENT_QUOTED', (new MySqlParser(mode: new SqlMode(ansiQuotes: true)))->tokenize('SELECT "x"')[1]->name);
        self::assertSame('TEXT_STRING', (new MySqlParser())->tokenize('SELECT "x"')[1]->name);
    }

    public function testTokenizeRejectsAnUnterminatedString(): void
    {
        $this->expectException(LexicalException::class);

        (new MySqlParser())->tokenize("SELECT 'abc");
    }

    public function testParse(): void
    {
        $sql = 'SELECT id, name FROM users u WHERE u.id = ? AND name LIKE \'a%\' ORDER BY id DESC LIMIT 10';
        $tree = (new MySqlParser())->parse($sql);

        self::assertSame('start_entry', $tree->name);
        self::assertSame($sql, $tree->text($sql));
        self::assertCount(1, $tree->find('where_clause'));
        self::assertSame('WHERE u.id = ? AND name LIKE \'a%\'', $tree->find('where_clause')[0]->text($sql));
    }

    public function testParseWritesTheTextBackWithItsCommentsAndWhitespace(): void
    {
        $sql = "  SELECT /* one */ 1 , 2 # two\nFROM  t  ;\n\n-- done\n";
        $versioned = 'SELECT 1 /*!40101 , 2 */ FROM t';

        $rollup = 'SELECT a FROM t GROUP BY a WITH  ROLLUP';

        self::assertSame($sql, (new MySqlParser())->parse($sql)->toString());
        self::assertSame($versioned, (new MySqlParser())->parse($versioned)->toString());
        self::assertSame($rollup, (new MySqlParser())->parse($rollup)->toString());
        self::assertSame('-- nothing at all', (new MySqlParser())->parse('-- nothing at all')->toString());
    }

    public function testParseFollowsTheChosenRelease(): void
    {
        self::assertSame('query', (new MySqlParser('mysql-5.6.51'))->parse('SELECT 1')->name);
        self::assertCount(1, (new MySqlParser('mysql-8.0.44'))->parse('WITH c AS (SELECT 1) SELECT * FROM c')->find('with_clause'));
    }

    public function testParseRejectsSyntaxTheReleaseLacks(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage("Unexpected 'WITH' at line 1, column 1");

        (new MySqlParser('mysql-5.6.51'))->parse('WITH c AS (SELECT 1) SELECT * FROM c');
    }

    public function testParseRejectsAStatementThatEndsEarly(): void
    {
        $this->expectException(SyntaxException::class);

        (new MySqlParser())->parse('SELECT 1 FROM');
    }

    public function testVersions(): void
    {
        $versions = MySqlParser::versions();

        self::assertSame('mysql-5.6.51', $versions[0]);
        self::assertContains('mysql-8.4.7', $versions);
        self::assertCount(9, $versions);
    }
}
