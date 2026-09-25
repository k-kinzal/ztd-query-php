<?php

declare(strict_types=1);

namespace Tests\Unit\Facade;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Dialect;

#[CoversClass(\SqlSemantics\Facade\Semantics::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Analyzer::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\ValueReader::class)]
#[UsesClass(\SqlSemantics\Core\AnalysisException::class)]
#[UsesClass(\SqlSemantics\Statement\Element::class)]
#[UsesClass(\SqlSemantics\Statement\Statement::class)]
#[UsesClass(\SqlSemantics\Statement\Writer::class)]
#[UsesClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[UsesClass(Dialect::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[Medium]
final class SemanticsTest extends TestCase
{
    #[TestWith([Dialect::MySql, 'CREATE LOGFILE GROUP logs ADD UNDOFILE \'undo.dat\''])]
    #[TestWith([Dialect::MySql, 'SET DEFAULT .some_variable = DEFAULT'])]
    #[TestWith([Dialect::MySql, 'SHOW COUNT( * ) ERRORS'])]
    #[TestWith([Dialect::MySql, 'CREATE TABLE count (id INT)'])]
    #[TestWith([Dialect::MySql, 'SELECT count (1), COUNT(*) FROM count'])]
    #[TestWith([Dialect::MySql, 'CREATE PROCEDURE p() BEGIN DECLARE n INT DEFAULT 1; WHILE n < 3 DO SET n = n + 1; END WHILE; END'])]
    #[TestWith([Dialect::MySql, 'WITH RECURSIVE t(n) AS (SELECT 1 UNION ALL SELECT n+1 FROM t WHERE n<4) SELECT SUM(n) OVER (ORDER BY n) FROM t'])]
    #[TestWith([Dialect::MySql, 'INSERT INTO t (id, name) VALUES (1, \'a\') ON DUPLICATE KEY UPDATE name = VALUES(name)'])]
    #[TestWith([Dialect::MySql, 'SELECT `select`.id, @@global.sql_mode FROM db.`from`'])]
    #[TestWith([Dialect::PostgreSql, 'CREATE LANGUAGE lang HANDLER handle_lang'])]
    #[TestWith([Dialect::PostgreSql, 'DO \'text\' \'text\''])]
    #[TestWith([Dialect::PostgreSql, 'MERGE INTO t USING u ON t.id = u.id WHEN MATCHED THEN UPDATE SET v = u.v WHEN NOT MATCHED THEN INSERT (id) VALUES (u.id)'])]
    #[TestWith([Dialect::PostgreSql, 'SELECT SUM(n) FILTER (WHERE n > 1) OVER (PARTITION BY k ORDER BY n ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) FROM t'])]
    #[TestWith([Dialect::PostgreSql, 'CREATE TABLE t (id INT GENERATED ALWAYS AS IDENTITY, value TEXT DEFAULT \'x\', CHECK(id > 0))'])]
    #[TestWith([Dialect::PostgreSql, 'GRANT SELECT ON TABLE t TO r; REVOKE SELECT ON TABLE t FROM r'])]
    #[TestWith([Dialect::Sqlite, 'CREATE VIRTUAL TABLE docs USING fts5(title, body)'])]
    #[TestWith([Dialect::Sqlite, 'CREATE TRIGGER tr AFTER INSERT ON t BEGIN UPDATE u SET n = n + 1 WHERE id = new.id; DELETE FROM log; END'])]
    #[TestWith([Dialect::Sqlite, 'INSERT INTO t (id) VALUES (1) ON CONFLICT(id) DO UPDATE SET id = excluded.id RETURNING id'])]
    #[TestWith([Dialect::Sqlite, 'SELECT CASE WHEN n IS NULL THEN 0 ELSE n END, ROW_NUMBER() OVER (ORDER BY n) FROM t LEFT JOIN u USING (id)'])]
    #[TestWith([Dialect::Sqlite, 'ALTER TABLE t ADD name TEXT REFERENCES other(id) ON UPDATE CASCADE'])]
    public function testAnalyzeRoundTripsCompleteStatements(Dialect $dialect, string $sql): void
    {
        $statement = (new \SqlSemantics\Facade\Semantics($dialect))->analyze($sql);
        $formatter = new \SqlFormatter\Facade\Formatter($dialect->platform()->parser(), new \SqlFormatter\Core\FormatOptions(\SqlFormatter\Core\Style::Compact));
        self::assertSame($formatter->format($sql), $formatter->format($statement->toString()));
    }

    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.1.0'])]
    #[TestWith(['mysql-8.2.0'])]
    #[TestWith(['mysql-8.3.0'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.0.1'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testAnalyzeWithEveryGrammarRelease(string $version): void
    {
        $statement = (new \SqlSemantics\Facade\Semantics(Dialect::MySql, $version))->analyze('DELETE FROM absent_table WHERE id = 1');
        self::assertSame('DELETE FROM absent_table WHERE id = 1', $statement->toString());
    }

    #[TestWith(['mysql-5.6.51', 'ALTER PROCEDURE ACTION .some_name'])]
    #[TestWith(['mysql-5.7.44', 'ALTER DEFINER = \'text\' EVENT SQL_AFTER_GTIDS .some_name RENAME TO ACTION'])]
    public function testAnalyzePreservesKeywordNamesBeforeDots(string $version, string $sql): void
    {
        $statement = (new \SqlSemantics\Facade\Semantics(Dialect::MySql, $version))->analyze($sql);
        $formatter = new \SqlFormatter\Facade\Formatter(Dialect::MySql->platform()->parser($version), new \SqlFormatter\Core\FormatOptions(\SqlFormatter\Core\Style::Compact));
        self::assertSame($formatter->format($sql), $formatter->format($statement->toString()));
    }

    #[TestWith(['mysql-5.6.51', 'statement', "\x18\x00\x00\x00\x00\x00\x15\x00\x01\x00\x00\x00\x00\x00\x01\x00\x00\x00\x00\x00\x03\x00\x00\x00\x00\x00\x01\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x01\x00\x00\x00\x00\x00\x00\x00\x01\x00\x00\x00\x00\x00\x00\x00\x00"])]
    #[TestWith(['mysql-9.1.0', 'simple_statement_or_begin', "\x18\x00\x00\x00\x00\x00\x15\x00\x01\x00\x00\x00\x00\x00\x01\x00\x00\x00\x00\x00\x03\x00\x00\x00\x00\x00\x01\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x01\x00\x00\x00\x00\x00\x00\x00\x02\x00\x00\x00\x00\x00\x00\x00\x00"])]
    #[TestWith(['mysql-5.6.51', 'statement', "\x9e\x06\x00\x00\xeb\x25\x4b\xc3\xea\xf2\xf0\x9e\xea\x25\xaa\x39\x51\x9f\xcc\x54\xff\x01\xfc\xb4\x51\x47\x87\x7b\xbb\xc2\x02\xcd\x5f\x6a\xad\xb6\x44\xdd\x96\xe3\xcf\x41\x38\x8f\x2c\x08\x5e\x0f\xe3\xad\xf7\x0c\x1c\x33\x23\x51\x3a\x6d\x8e\x94\x93\xe0\x44\xee\xb4\xcf\x91\x7b\x27\x3f\x2d\xf4\x55\x47\x69\x9a\xf7\x95\x32\xfe\xc4\xb5\x4c\x6d\xb4\x26\x26\x34\xe0\xc3\x55\xa4\xef\xe2\x05\x62\x35\xc2\x72\x64\xfe\x16\x23\x2d\xe6\x0d\x5a\xbf\xa8\x33\xa9\xd0\x84\x33\xe8\x4a\x6c\x20\x39\x62\x48\x97\x0c\xf0\xb6\xae\x15\xe4\x5a\xb4\xbb\x17"])]
    public function testAnalyzeFakerRegressions(string $version, string $root, string $input): void
    {
        $provider = new \SqlFaker\MySql\MySqlProvider(\Faker\Factory::create(), $version);
        $constraints = \SqlFaker\Generation\Plan\GenerationPlan::fromRule($root)->requiringNonEmpty();
        $plan = (new \SqlFaker\Generation\Choice\BytePlanCompiler())->compile($input, $provider->planner(), $constraints);
        $sql = $provider->generate($plan);
        $statement = (new \SqlSemantics\Facade\Semantics(Dialect::MySql, $version))->analyze($sql);
        $formatter = new \SqlFormatter\Facade\Formatter(Dialect::MySql->platform()->parser($version), new \SqlFormatter\Core\FormatOptions(\SqlFormatter\Core\Style::Compact));
        self::assertSame($formatter->format($sql), $formatter->format($statement->toString()));
    }
}
