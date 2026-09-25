<?php

declare(strict_types=1);

namespace Tests\Unit\Compact;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlFormatter\Compact\Document;
use SqlFormatter\Compact\Grouping;
use SqlFormatter\Compact\Keywords;
use SqlFormatter\Compact\Renderer;
use SqlFormatter\Compact\Rules;
use SqlFormatter\Compact\Shape;
use SqlFormatter\Compact\Spacing;
use SqlFormatter\Compact\Trivia;
use SqlFormatter\Compact\Visitor;
use SqlParser\MySql\MySqlParser;
use SqlParser\MySql\SqlMode;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlParser\Sqlite\SqliteParser;

#[CoversClass(Renderer::class)]
#[CoversClass(\SqlFormatter\Compact\Reductions::class)]
#[CoversClass(Document::class)]
#[CoversClass(Grouping::class)]
#[CoversClass(Keywords::class)]
#[CoversClass(Rules::class)]
#[CoversClass(Shape::class)]
#[CoversClass(Spacing::class)]
#[CoversClass(Trivia::class)]
#[CoversClass(Visitor::class)]
final class RendererTest extends TestCase
{
    /**
     * @param class-string<MySqlParser|PostgreSqlParser|SqliteParser> $parserClass
     */
    #[DataProvider('providerCanonicalForms')]
    public function testRenderEquivalentSpellings(string $parserClass, ?string $version, string $input, string $expected): void
    {
        $parser = new $parserClass($version);
        $renderer = new Renderer($parser);
        self::assertSame($expected, $renderer->render($parser->parse($input)));
        self::assertSame($expected, $renderer->render($parser->parse($expected)));
    }

    /**
     * @return iterable<string, array{class-string<MySqlParser|PostgreSqlParser|SqliteParser>, string|null, string, string}>
     */
    public static function providerCanonicalForms(): iterable
    {
        $queries = [
            ['select all ((a)) as x, b from t as q left outer join u on q.a != u.a order by b asc;', 'SELECT a x,b FROM t q LEFT JOIN u ON q.a<>u.a ORDER BY b'],
            ['SELECT a x,b FROM t q LEFT JOIN u ON q.a<>u.a ORDER BY b', 'SELECT a x,b FROM t q LEFT JOIN u ON q.a<>u.a ORDER BY b'],
            ["-- before\nselect /* ordinary */ a, b from t -- after\nwhere a = 1; /* tail */", 'SELECT a,b FROM t WHERE a=1'],
            ['select a from t inner join u on (t.a=u.a)', 'SELECT a FROM t JOIN u ON t.a=u.a'],
            ['SELECT a FROM t NATURAL INNER JOIN u', 'SELECT a FROM t NATURAL JOIN u'],
            ['SELECT a FROM t CROSS JOIN u', 'SELECT a FROM t CROSS JOIN u'],
            ['SELECT (a+b)*c AS x,a+(b*c) AS y,a-(b-c) AS z FROM t WHERE (a=1 AND b=2)', 'SELECT(a+b)*c x,a+b*c y,a-(b-c)z FROM t WHERE a=1 AND b=2'],
            ['SELECT (a-b)-c AS x FROM t WHERE (a=1 OR b=2) AND c=3', 'SELECT a-b-c x FROM t WHERE(a=1 OR b=2)AND c=3'],
            ['SELECT 1 - -2 AS x', 'SELECT 1- -2 x'],
            ['select t . a from Action as t', 'SELECT t.a FROM Action t'],
            ["select '/* literal */ -- text' as x", "SELECT'/* literal */ -- text'x"],
            ['WITH q AS (SELECT 1 AS x) SELECT x FROM q', 'WITH q AS(SELECT 1 x)SELECT x FROM q'],
            ['SELECT a FROM t UNION ALL SELECT a FROM u', 'SELECT a FROM t UNION ALL SELECT a FROM u'],
            ['SELECT a FROM t ORDER BY a DESC', 'SELECT a FROM t ORDER BY a DESC'],
            ['select /*+ KEEP(a) */ distinct a from t', 'SELECT/*+ KEEP(a) */DISTINCT a FROM t'],
        ];
        $releases = [
            ...array_map(static fn (string $version): array => [MySqlParser::class, $version], MySqlParser::versions()),
            [PostgreSqlParser::class, null], [SqliteParser::class, null],
        ];
        foreach ($releases as [$parser, $version]) {
            foreach ($queries as $index => [$input, $expected]) {
                if (in_array($index, [4, 11], true) && in_array($version, ['mysql-5.6.51', 'mysql-5.7.44'], true)) {
                    continue;
                }
                yield $parser . '-' . $version . '-' . $index => [$parser, $version, $input, $expected];
            }
        }
        foreach ([MySqlParser::class, PostgreSqlParser::class] as $parser) {
            yield $parser . '-set-distinct' => [$parser, null, 'SELECT a FROM t UNION DISTINCT SELECT a FROM u', 'SELECT a FROM t UNION SELECT a FROM u'];
        }
        yield 'sqlite-trailing-terminators' => [SqliteParser::class, null, 'SELECT 1;;; -- tail', 'SELECT 1'];
        yield 'pg-trailing-terminators' => [PostgreSqlParser::class, null, 'SELECT 1;;; -- tail', 'SELECT 1'];
        yield 'pg-empty-statements' => [PostgreSqlParser::class, null, ';;;', ''];
        yield 'sqlite-empty-statements' => [SqliteParser::class, null, ';;;', ''];
        yield 'sqlite-equals' => [SqliteParser::class, null, 'SELECT a FROM t WHERE a == 1 AND b != 2;', 'SELECT a FROM t WHERE a=1 AND b<>2'];
        yield 'sqlite-statements' => [SqliteParser::class, null, 'SELECT 1; SELECT 2;', 'SELECT 1;SELECT 2'];
        yield 'sqlite-identifier-fallback' => [SqliteParser::class, null, 'select Action from Action', 'SELECT Action FROM Action'];
        yield 'sqlite-cast' => [SqliteParser::class, null, 'select cast(a as text) as v from t', 'SELECT CAST(a AS text)v FROM t'];
        yield 'pg-keyword-alias' => [PostgreSqlParser::class, null, 'SELECT a AS value FROM t', 'SELECT a value FROM t'];
        yield 'pg-mandatory-alias' => [PostgreSqlParser::class, null, 'SELECT 1 AS from', 'SELECT 1 AS from'];
        yield 'mysql-merged-keywords' => [MySqlParser::class, null, 'select a from t group by a with /* remove */ rollup', 'SELECT a FROM t GROUP BY a WITH ROLLUP'];
        yield 'pg-cast' => [PostgreSqlParser::class, null, 'select cast(a as text) as v from t', 'SELECT CAST(a AS text)v FROM t'];
        yield 'pg-required-alias' => [PostgreSqlParser::class, null, 'select 1 as "select"', 'SELECT 1"select"'];
        yield 'pg-nested-comment' => [PostgreSqlParser::class, null, 'SELECT /* outer /*+ hidden hint */ body */ 1', 'SELECT 1'];
        yield 'pg-string-continuation' => [PostgreSqlParser::class, null, "SELECT 'a'\n'b' AS v;", "SELECT'a'\n'b'v"];
        yield 'pg-dollar-literal' => [PostgreSqlParser::class, null, 'select $tag$ /* keep */ $tag$ as v', 'SELECT $tag$ /* keep */ $tag$v'];
        yield 'pg-array' => [PostgreSqlParser::class, null, 'SELECT ARRAY[1,2], $1::text', 'SELECT ARRAY[1,2],$1::text'];
        yield 'pg-group-distinct' => [PostgreSqlParser::class, null, 'SELECT a FROM t GROUP BY DISTINCT a', 'SELECT a FROM t GROUP BY DISTINCT a'];
        yield 'mysql-distinctrow' => [MySqlParser::class, null, 'select distinctrow a from t where a regexp b', 'SELECT DISTINCT a FROM t WHERE a RLIKE b'];
        yield 'mysql-string-alias' => [MySqlParser::class, null, "SELECT 'a' AS 'b'", "SELECT'a'AS'b'"];
        yield 'mysql-string-concatenation' => [MySqlParser::class, null, "SELECT 'a' 'b'", "SELECT'a' 'b'"];
        yield 'mysql-type-alias' => [MySqlParser::class, null, 'create table t (a integer,b decimal(8,2))', 'CREATE TABLE t(a INT,b DEC(8,2))'];
        yield 'mysql-function-gap' => [MySqlParser::class, null, 'SELECT count(1), count (2)', 'SELECT COUNT(1),count (2)'];
        yield 'mysql-variable' => [MySqlParser::class, null, 'SELECT @name, @@session.sql_mode', 'SELECT@name,@@SESSION.sql_mode'];
        yield 'mysql-qualification' => [MySqlParser::class, null, 'SHOW CREATE TABLE ACTION ._sqlfaker_identifier', 'SHOW CREATE TABLE ACTION ._sqlfaker_identifier'];
        yield 'mysql-hint' => [MySqlParser::class, null, 'SELECT /* ordinary */ /*+ MAX_EXECUTION_TIME(1000) */ `order` FROM t;', 'SELECT/*+ MAX_EXECUTION_TIME(1000) */`order`FROM t'];
        yield 'mysql-active-comment' => [MySqlParser::class, null, 'select /*!80000 DISTINCT */ a from t; # tail', 'SELECT/*!80000 DISTINCT */a FROM t'];
        yield 'mysql-inactive-comment' => [MySqlParser::class, null, 'select /*!99999 SQL_NO_CACHE */ a from t;', 'SELECT/*!99999 SQL_NO_CACHE */a FROM t'];
        yield 'mysql-nested-executable-body' => [MySqlParser::class, null, '/*!80000 select 1 /* keep */ + 2 */', '/*!80000 select 1 /* keep */ + 2 */'];
        yield 'mysql-executable-body' => [MySqlParser::class, null, '/*!80000 select  (1 + 2) */', '/*!80000 select  (1 + 2) */'];
    }

    public function testRenderHonorsMysqlLexicalModes(): void
    {
        $parser = new MySqlParser(mode: new SqlMode(ansiQuotes: true, pipesAsConcat: true, ignoreSpace: true));
        $renderer = new Renderer($parser);
        self::assertSame('SELECT COUNT(1),"name"||\'!\'FROM"users"', $renderer->render($parser->parse('select count (1), "name" || \'!\' from "users";')));
    }

    #[DataProvider('providerSqliteExecution')]
    public function testRenderPreservesSqliteResultsAndOperatorAssociation(string $sql): void
    {
        $database = new PDO('sqlite::memory:');
        $database->exec('CREATE TABLE t(a INT,b INT,c INT); INSERT INTO t VALUES(1,2,3),(2,NULL,4),(5,3,2)');
        $parser = new SqliteParser();
        $renderer = new Renderer($parser);
        $original = $database->query($sql);
        $formatted = $database->query($renderer->render($parser->parse($sql)));
        self::assertNotFalse($original);
        self::assertNotFalse($formatted);
        self::assertSame($original->fetchAll(PDO::FETCH_ASSOC), $formatted->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @return list<array{string}>
     */
    public static function providerSqliteExecution(): array
    {
        return [
            ['SELECT (a+b)*c AS x,a+(b*c) AS y,a-(b-c) AS z FROM t ORDER BY a ASC'],
            ['SELECT a AS x FROM t WHERE (a=1 OR b=3) AND c=2 ORDER BY a ASC'],
            ['SELECT a AS x FROM t WHERE NOT (b=3 OR c=4) ORDER BY a ASC'],
            ['SELECT a/(b/c) AS x FROM t ORDER BY a ASC'],
            ['SELECT a AS x FROM t WHERE a == 1 OR a != 2 ORDER BY a ASC'],
        ];
    }

    public function testWriteRetainsDirectiveOwnershipAndRequiredSeparators(): void
    {
        $renderer = new Renderer(new SqliteParser());
        self::assertSame('SELECT/*+ keep */a FROM t', $renderer->write([
            new \SqlParser\Lexer\Token(1, 'SELECT', 'SELECT', 0),
            new \SqlParser\Lexer\Token(2, 'ID', 'a', 20, '/*+ keep */'),
            new \SqlParser\Lexer\Token(3, 'FROM', 'FROM', 22),
            new \SqlParser\Lexer\Token(2, 'ID', 't', 27),
        ], ''));
    }

    public function testDocumentSelectsDialectSpecificSpellings(): void
    {
        $parser = new MySqlParser();
        $document = (new Renderer($parser))->document($parser->parse('SELECT a FROM t WHERE a REGEXP b'));
        self::assertContains('RLIKE', array_column($document->tokens, 'text'));
    }
}
