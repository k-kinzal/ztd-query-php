<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlFormatter\FormatOptions;
use SqlFormatter\Formatter;
use SqlFormatter\Style;
use SqlParser\MySql\MySqlParser;
use SqlParser\MySql\SqlMode;
use SqlParser\Parser\SyntaxException;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlParser\Sqlite\SqliteParser;

#[CoversClass(Formatter::class)]
#[CoversClass(FormatOptions::class)]
#[CoversClass(\SqlFormatter\Layout\Renderer::class)]
#[CoversClass(\SqlFormatter\Layout\Block::class)]
#[CoversClass(\SqlFormatter\Layout\Elements::class)]
#[CoversClass(\SqlFormatter\Layout\Headers::class)]
#[CoversClass(\SqlFormatter\Layout\Policy::class)]
#[CoversClass(\SqlFormatter\Layout\Spacing::class)]
#[CoversClass(\SqlFormatter\Layout\Writer::class)]
#[CoversClass(\SqlFormatter\Syntax\Analyzer::class)]
#[CoversClass(\SqlFormatter\Syntax\Document::class)]
#[CoversClass(\SqlFormatter\Syntax\Brackets::class)]
#[CoversClass(\SqlFormatter\Syntax\Headers::class)]
#[CoversClass(\SqlFormatter\Syntax\Markers::class)]
#[CoversClass(\SqlFormatter\Syntax\Expressions::class)]
#[CoversClass(\SqlFormatter\Syntax\Fingerprint::class)]
#[CoversClass(\SqlFormatter\Syntax\Rules::class)]
#[Medium]
#[CoversClass(\SqlFormatter\Syntax\Lists::class)]
#[CoversClass(\SqlFormatter\Compact\Reductions::class)]
#[CoversClass(\SqlFormatter\Compact\Document::class)]
#[CoversClass(\SqlFormatter\Compact\Visitor::class)]
#[CoversClass(\SqlFormatter\Compact\Rules::class)]
#[CoversClass(\SqlFormatter\Compact\Keywords::class)]
#[CoversClass(\SqlFormatter\Compact\Grouping::class)]
#[CoversClass(\SqlFormatter\Compact\Shape::class)]
#[CoversClass(\SqlFormatter\Compact\Renderer::class)]
#[CoversClass(\SqlFormatter\Compact\Spacing::class)]
#[CoversClass(\SqlFormatter\Compact\Trivia::class)]
final class FormatterTest extends TestCase
{
    /**
     * @param class-string<MySqlParser|PostgreSqlParser|SqliteParser> $parserClass
     */
    #[DataProvider('providerStyles')]
    public function testFormatFourLayouts(string $parserClass, ?string $version, Style $style, string $expected): void
    {
        $formatter = new Formatter(new $parserClass($version), new FormatOptions($style));
        self::assertSame($expected, $formatter->format('SELECT id,name FROM users WHERE active=1 AND age>=18 ORDER BY name;'));
        self::assertSame($expected, $formatter->format($expected));
    }

    /**
     * @return iterable<string, array{class-string<MySqlParser|PostgreSqlParser|SqliteParser>, string|null, Style, string}>
     */
    public static function providerStyles(): iterable
    {
        $layouts = [
            'compact' => 'SELECT id,name FROM users WHERE active=1 AND age>=18 ORDER BY name',
            'expanded' => "SELECT\n    id,\n    name\nFROM\n    users\nWHERE\n    active = 1\n    AND age >= 18\nORDER BY\n    name;",
            'tabular' => "SELECT   id,\n         name\nFROM     users\nWHERE    active = 1\nAND      age >= 18\nORDER BY name;",
            'river' => "  SELECT id,\n         name\n    FROM users\n   WHERE active = 1\n     AND age >= 18\nORDER BY name;",
        ];
        foreach ([...MySqlParser::versions(), 'pg', 'sqlite'] as $dialect) {
            foreach ($layouts as $style => $expected) {
                yield $dialect . '-' . $style => [match ($dialect) {
                    'pg' => PostgreSqlParser::class, 'sqlite' => SqliteParser::class, default => MySqlParser::class
                }, str_starts_with($dialect, 'mysql-') ? $dialect : null, Style::from($style), $expected];
            }
        }
    }

    /**
     * @param class-string<MySqlParser|PostgreSqlParser|SqliteParser> $parserClass
     */
    #[DataProvider('providerTokenPreservation')]
    public function testFormatPreservesTokensAndIsIdempotent(string $parserClass, ?string $version, Style $style, string $sql): void
    {
        $parser = new $parserClass($version);
        $formatter = new Formatter($parser, new FormatOptions($style));
        $result = $formatter->format($sql);
        self::assertSame(array_column($parser->parse($sql)->tokens(), 'text'), array_column($parser->parse($result)->tokens(), 'text'));
        self::assertSame($result, $formatter->format($result));
    }

    /**
     * @param class-string<MySqlParser|PostgreSqlParser|SqliteParser> $parserClass
     */
    #[DataProvider('providerPreservation')]
    public function testFormatRemainsCanonicalAcrossLayouts(string $parserClass, ?string $version, Style $style, string $sql): void
    {
        $parser = new $parserClass($version);
        $layout = new Formatter($parser, new FormatOptions($style));
        $compact = new Formatter($parser, new FormatOptions(Style::Compact));
        self::assertSame($compact->format($sql), $compact->format($layout->format($sql)));
    }

    /**
     * @return iterable<string, array{class-string<MySqlParser|PostgreSqlParser|SqliteParser>, string|null, Style, string}>
     */
    public static function providerTokenPreservation(): iterable
    {
        foreach (self::providerPreservation() as $name => $case) {
            if ($case[2] !== Style::Compact) {
                yield $name => $case;
            }
        }
    }

    /**
     * @return iterable<string, array{class-string<MySqlParser|PostgreSqlParser|SqliteParser>, string|null, Style, string}>
     */
    public static function providerPreservation(): iterable
    {
        $common = [
            'SELECT a,b FROM t WHERE a BETWEEN 1 AND 3 AND b NOT BETWEEN 4 AND 5;',
            'SELECT a FROM t WHERE a IN (1,2) AND (b=2 OR c=3);',
            'SELECT coalesce(a,b), count(*), sum(a+1) FROM t GROUP BY a,b HAVING count(*)>1;',
            'SELECT a FROM t UNION ALL SELECT b FROM u ORDER BY a;',
            'SELECT CASE WHEN a=1 THEN CASE WHEN b=2 THEN 3 ELSE 4 END ELSE 5 END AS n FROM t;',
            'SELECT (SELECT max(a) FROM t) AS x, name FROM u;',
            'SELECT t.a,u.b FROM t LEFT OUTER JOIN u ON t.a=u.a AND u.b>0;',
            'INSERT INTO t (a,b) VALUES (1,2),(3,4);',
            'INSERT INTO t (a,b) SELECT a,b FROM u;',
            'UPDATE t SET a=1,b=b+1 WHERE c=3;',
            'DELETE FROM t WHERE a=1;',
            'CREATE TABLE t (id INTEGER PRIMARY KEY, name VARCHAR(20), n INT DEFAULT -1, CHECK (n>0));',
            'CREATE INDEX ix ON t (a,b);',
            'ALTER TABLE t ADD COLUMN score INT;',
            'DROP TABLE t;',
            'BEGIN;',
            'COMMIT;',
            "SELECT /* keep */ 'a  b', 'x; y' AS v FROM t -- tail\nWHERE a=1; -- final\n",
            "-- lead\nSELECT 1 /* block\n  body */; /* end */\n",
            'SELECT 1 - -2, +3, -4, 5/2, a.b FROM t;',
        ];
        foreach (['mysql-8.4.7', 'pg', 'sqlite'] as $dialect) {
            foreach (Style::cases() as $style) {
                foreach ($common as $index => $sql) {
                    yield $dialect . '-' . $style->value . '-' . $index => [match ($dialect) {
                        'pg' => PostgreSqlParser::class, 'sqlite' => SqliteParser::class, 'mysql-8.4.7' => MySqlParser::class
                    }, str_starts_with($dialect, 'mysql-') ? $dialect : null, $style, $sql];
                }
            }
        }
        $specific = [
            'mysql-8.4.7' => ['SELECT /*!80000 DISTINCT */ a FROM t;', 'SELECT /*+ MAX_EXECUTION_TIME(1000) */ `order`, @name FROM t;', 'SELECT 1; # final', 'SELECT count(1), count (2);', 'SHOW CREATE TABLE ACTION ._sqlfaker_identifier'],
            'pg' => ["SELECT 'a'\n'b', \$tag\$keep\nthis\$tag\$;", 'SELECT a::text, $1, ARRAY[1,2] FROM t;', 'SELECT 1; SELECT 2;', 'SELECT U&\'d!0061t\' UESCAPE \'!\';'],
            'sqlite' => ['SELECT [select], :name, ?1 FROM t;', 'SELECT 1; SELECT 2;', 'CREATE TRIGGER tr AFTER INSERT ON t BEGIN UPDATE t SET a=1; UPDATE t SET b=2; END;'],
        ];
        foreach ($specific as $dialect => $queries) {
            foreach (Style::cases() as $style) {
                foreach ($queries as $index => $sql) {
                    yield $dialect . '-special-' . $style->value . '-' . $index => [match ($dialect) {
                        'pg' => PostgreSqlParser::class, 'sqlite' => SqliteParser::class, 'mysql-8.4.7' => MySqlParser::class
                    }, str_starts_with($dialect, 'mysql-') ? $dialect : null, $style, $sql];
                }
            }
        }
    }

    #[DataProvider('providerVersions')]
    public function testFormatEveryGrammarRelease(string $version, Style $style): void
    {
        $formatter = new Formatter(new MySqlParser($version), new FormatOptions($style));
        $output = $formatter->format('SELECT a,b FROM t WHERE a=1 ORDER BY b;');
        self::assertSame($output, $formatter->format($output));
        self::assertStringContainsString('SELECT', $output);
    }

    /**
     * @return iterable<string, array{string, Style}>
     */
    public static function providerVersions(): iterable
    {
        foreach (MySqlParser::versions() as $version) {
            foreach (Style::cases() as $style) {
                yield $version . '-' . $style->value => [$version, $style];
            }
        }
    }

    /**
     * @param class-string<MySqlParser|PostgreSqlParser|SqliteParser> $parserClass
     */
    #[DataProvider('providerGeneratedStatements')]
    public function testFormatGeneratedStatements(string $parserClass, string $version, Style $style, string $sql): void
    {
        $formatter = new Formatter(new $parserClass($version), new FormatOptions($style));
        $output = $formatter->format($sql);
        self::assertSame($output, $formatter->format($output));
        $compact = new Formatter(new $parserClass($version), new FormatOptions(Style::Compact));
        self::assertSame($compact->format($sql), $compact->format($output));
    }

    /**
     * @return iterable<string, array{class-string<MySqlParser|PostgreSqlParser|SqliteParser>, string, Style, string}>
     */
    public static function providerGeneratedStatements(): iterable
    {
        $faker = \Faker\Factory::create();
        $faker->seed(927);
        $releases = [
            ...array_map(static fn (string $version): array => [MySqlParser::class, \SqlFaker\MySqlProvider::class, $version, in_array($version, ['mysql-5.6.51', 'mysql-5.7.44'], true) ? 'statement' : 'simple_statement_or_begin'], MySqlParser::versions()),
            [PostgreSqlParser::class, \SqlFaker\PostgreSqlProvider::class, 'pg-17.2', 'stmt'],
            [SqliteParser::class, \SqlFaker\SqliteProvider::class, 'sqlite-3.47.2', 'cmd'],
        ];
        foreach ($releases as [$parserClass, $providerClass, $version, $root]) {
            $provider = new $providerClass($faker, $version);
            $plan = \SqlFaker\Generation\Plan\GenerationPlan::fromRule($root)->requiringNonEmpty()->withMaxDepth(6);
            for ($index = 0; $index < 16; $index++) {
                $sql = $provider->generate($plan);
                foreach (Style::cases() as $style) {
                    yield $version . '-' . $index . '-' . $style->value => [$parserClass, $version, $style, $sql];
                }
            }
        }
    }

    /**
     * @param class-string<MySqlParser|PostgreSqlParser|SqliteParser> $parserClass
     */
    #[DataProvider('providerExpandedLayouts')]
    public function testFormatExpandedStructures(string $parserClass, string $sql, string $expected): void
    {
        self::assertSame($expected, (new Formatter(new $parserClass()))->format($sql));
    }

    /**
     * @return iterable<string, array{class-string<MySqlParser|PostgreSqlParser|SqliteParser>, string, string}>
     */
    public static function providerExpandedLayouts(): iterable
    {
        $queries = [
            ['SELECT a,b FROM t GROUP BY a,b ORDER BY a,b;', "SELECT\n    a,\n    b\nFROM\n    t\nGROUP BY\n    a,\n    b\nORDER BY\n    a,\n    b;"],
            ['INSERT INTO t (a,b) VALUES (1,2),(3,4);', "INSERT INTO\n    t (a, b)\nVALUES\n    (1, 2),\n    (3, 4);"],
            ['UPDATE t SET a=1,b=b+1 WHERE c=3;', "UPDATE\n    t\nSET\n    a = 1,\n    b = b + 1\nWHERE\n    c = 3;"],
            ['DELETE FROM t WHERE a=1;', "DELETE FROM\n    t\nWHERE\n    a = 1;"],
            ['CREATE TABLE t (id INT, name VARCHAR(20));', "CREATE TABLE t (\n    id INT,\n    name VARCHAR(20)\n);"],
            ['SELECT a FROM t LEFT OUTER JOIN u ON t.a=u.a;', "SELECT\n    a\nFROM\n    t\nLEFT OUTER JOIN\n    u\nON\n    t.a = u.a;"],
            ['SELECT CASE WHEN a BETWEEN 1 AND 3 THEN 1 ELSE 0 END FROM t;', "SELECT\n    CASE\n        WHEN a BETWEEN 1 AND 3 THEN 1\n        ELSE 0\n    END\nFROM\n    t;"],
            ['SELECT a FROM t UNION ALL SELECT b FROM u;', "SELECT\n    a\nFROM\n    t\nUNION ALL\nSELECT\n    b\nFROM\n    u;"],
            ['SELECT row_number() OVER (PARTITION BY a,b ORDER BY b ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) AS n FROM t;', "SELECT\n    row_number() OVER (\n        PARTITION BY\n            a,\n            b\n        ORDER BY\n            b\n        ROWS\n            BETWEEN 1 PRECEDING AND CURRENT ROW\n    ) AS n\nFROM\n    t;"],
        ];
        foreach ([MySqlParser::class, PostgreSqlParser::class, SqliteParser::class] as $parserClass) {
            foreach ($queries as $index => [$sql, $expected]) {
                yield $parserClass . '-' . $index => [$parserClass, $sql, $expected];
            }
        }
    }

    public function testFormatNestedCteWithConfiguredIndent(): void
    {
        $formatter = new Formatter(new SqliteParser(), new FormatOptions(Style::Expanded, 2));
        self::assertSame("WITH\n  q AS (\n    SELECT\n      a,\n      b\n    FROM\n      t\n  )\nSELECT\n  *\nFROM\n  q;", $formatter->format('WITH q AS (SELECT a,b FROM t) SELECT * FROM q;'));
    }

    public function testFormatPreservesMysqlSqlMode(): void
    {
        $formatter = new Formatter(new MySqlParser(mode: new SqlMode(ansiQuotes: true, pipesAsConcat: true)), new FormatOptions(Style::Compact));
        self::assertSame('SELECT"name"||\'!\'FROM"users"', $formatter->format('SELECT "name"||\'!\' FROM "users";'));
    }

    public function testFormatRejectsInvalidInput(): void
    {
        $formatter = new Formatter(new SqliteParser());
        $this->expectException(SyntaxException::class);
        $formatter->format('SELECT FROM');
    }
}
