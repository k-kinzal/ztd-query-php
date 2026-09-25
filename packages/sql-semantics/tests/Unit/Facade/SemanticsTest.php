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
}
