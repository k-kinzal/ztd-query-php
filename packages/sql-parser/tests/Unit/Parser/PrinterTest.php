<?php

declare(strict_types=1);

namespace Tests\Unit\Parser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlParser\MySql\MySqlParser;
use SqlParser\MySql\SqlMode;
use SqlParser\Parser\Node;
use SqlParser\Parser\Printer;
use SqlParser\Parser\Separator;
use SqlParser\Parser\Spacing;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlParser\Sqlite\SqliteParser;

#[CoversClass(Printer::class)]
#[UsesClass(Node::class)]
#[UsesClass(Separator::class)]
#[UsesClass(Spacing::class)]
#[UsesClass(Token::class)]
#[Small]
final class PrinterTest extends TestCase
{
    public function testOfBuildsAPrinterThatWritesForOneDialect(): void
    {
        self::assertSame('SELECT 1', Printer::of(new SqliteParser())->render((new SqliteParser())->parse('SELECT   1')));
    }

    public function testRenderWritesAnEmptyTreeAsNothing(): void
    {
        self::assertSame('', Printer::of(new SqliteParser())->render(new Node('opt', 0, [])));
    }

    public function testRenderWritesOneTokenOnItsOwn(): void
    {
        self::assertSame('1', Printer::of(new SqliteParser())->render(new Token(1, 'INTEGER', '1', 0)));
    }

    public function testRenderLeavesOutTokensWithNoText(): void
    {
        $tree = (new SqliteParser())->parse('SELECT 1');

        self::assertSame('SELECT 1', Printer::of(new SqliteParser())->render($tree));
    }

    #[DataProvider('providerStatements')]
    public function testRenderWritesAStatementBackOut(string $sql, string $expected): void
    {
        self::assertSame($expected, Printer::of(new SqliteParser())->render((new SqliteParser())->parse($sql)));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerStatements(): array
    {
        return [
            'collapses the spacing it does not need' => ['SELECT  a,   b FROM t', 'SELECT a, b FROM t'],
            'keeps a call against its bracket' => ['SELECT count(*) FROM t', 'SELECT count(*) FROM t'],
            'keeps a qualified name whole' => ['SELECT t.a FROM t', 'SELECT t.a FROM t'],
            'drops a comment the lexer dropped' => ['SELECT /* why */ a FROM t', 'SELECT a FROM t'],
            'writes a semicolon that was written' => ['SELECT a FROM t;', 'SELECT a FROM t;'],
            'leaves out a semicolon that was supplied' => ['SELECT a FROM t', 'SELECT a FROM t'],
        ];
    }

    #[DataProvider('providerReadStatements')]
    public function testRenderWritesTextThatReadsBackAsTheSameTokens(MySqlParser|PostgreSqlParser|SqliteParser $parser, string $sql): void
    {
        $tree = $parser->parse($sql);
        $written = $parser->parse($parser->render($tree));

        self::assertSame(
            array_map(static fn (Token $token): string => $token->name . ' ' . $token->text, $tree->tokens()),
            array_map(static fn (Token $token): string => $token->name . ' ' . $token->text, $written->tokens()),
        );
    }

    /**
     * @return array<string, array{MySqlParser|PostgreSqlParser|SqliteParser, string}>
     */
    public static function providerReadStatements(): array
    {
        return [
            'call' => [new MySqlParser(), 'SELECT COUNT(*) FROM t'],
            'system variable' => [new MySqlParser(), 'SELECT @@session.sql_mode'],
            'user variable' => [new MySqlParser(), 'SELECT @x'],
            'keyword as a qualifier' => [new MySqlParser(), 'SELECT comment.a FROM comment'],
            'keyword standing apart from a dot' => [new MySqlParser(), 'SELECT t. comment FROM t'],
            'quoted name' => [new MySqlParser(), 'SELECT `x`.`y` FROM t'],
            'version comment' => [new MySqlParser(), 'SELECT /*!40001 SQL_NO_CACHE */ a FROM t'],
            'string holding punctuation' => [new MySqlParser(), "SELECT 'a,b.c(' FROM t"],
            'minus signs a comment could swallow' => [new MySqlParser(), 'SELECT 1--/**/2'],
            'operators of the dialect' => [new PostgreSqlParser(), 'UPDATE t SET a = a + 1 WHERE id IN (1,2) RETURNING a::text'],
            'an operator beside a sign' => [new PostgreSqlParser(), 'SELECT @ -1'],
            'a literal held against its letter' => [new PostgreSqlParser(), "SELECT N'abc'"],
            'a dot between two numbers' => [new SqliteParser(), 'CREATE VIRTUAL TABLE t USING m(1 . 2)'],
        ];
    }

    public function testRenderHoldsANameOffItsBracketWhereASpaceWouldBeIgnored(): void
    {
        $parser = new MySqlParser(mode: new SqlMode(ignoreSpace: true));
        $tree = $parser->parse('SELECT count/**/(1)');
        $written = $parser->parse($parser->render($tree));

        self::assertSame(
            array_map(static fn (Token $token): string => $token->name . ' ' . $token->text, $tree->tokens()),
            array_map(static fn (Token $token): string => $token->name . ' ' . $token->text, $written->tokens()),
        );
    }

    #[DataProvider('providerBuiltStatements')]
    public function testRenderWritesBuiltTokensSoTheyReadBackAsThemselves(MySqlParser|PostgreSqlParser|SqliteParser $parser, string $sql): void
    {
        $detach = static function (Node|Token $branch) use (&$detach): Node|Token {
            return $branch instanceof Token
                ? $branch->detached()
                /** @var callable(Node|Token): (Node|Token) $detach */
                : new Node($branch->name, $branch->ordinal, array_map($detach, $branch->children));
        };
        $tree = $parser->parse($sql);
        /** @var Node|Token $built */
        $built = $detach($tree);
        $written = $parser->parse($parser->render($built));

        self::assertSame(
            array_map(static fn (Token $token): string => $token->name . ' ' . $token->text, $tree->tokens()),
            array_map(static fn (Token $token): string => $token->name . ' ' . $token->text, $written->tokens()),
        );
    }

    /**
     * @return array<string, array{MySqlParser|PostgreSqlParser|SqliteParser, string}>
     */
    public static function providerBuiltStatements(): array
    {
        return [
            'qualified name' => [new MySqlParser(), 'SELECT users.id FROM users'],
            'call' => [new MySqlParser(), 'SELECT COUNT(*) FROM t'],
            'system variable' => [new MySqlParser(), 'SELECT @@session.sql_mode'],
            'a name that is a keyword' => [new MySqlParser(), 'CREATE TABLE count (i INT)'],
            'a keyword standing beside a dot' => [new MySqlParser(), 'DROP TRIGGER EXECUTE .SIGNED'],
            'a keyword on each side of a dot' => [new MySqlParser(), 'SELECT t. comment FROM t'],
            'an operator beside a sign' => [new PostgreSqlParser(), 'SELECT @ -1'],
            'two operators in a row' => [new PostgreSqlParser(), 'SELECT a @ @ b'],
            'a literal held against its letter' => [new PostgreSqlParser(), "SELECT N'abc'"],
            'a dot between two numbers' => [new SqliteParser(), 'CREATE VIRTUAL TABLE t USING m(1 . 2)'],
            'a variable beside a bracket' => [new SqliteParser(), 'CREATE VIRTUAL TABLE t USING m(:x (y))'],
            'rows of values' => [new SqliteParser(), "INSERT INTO t (a, b) VALUES (1, 'x'), (2, 'y')"],
        ];
    }
}
