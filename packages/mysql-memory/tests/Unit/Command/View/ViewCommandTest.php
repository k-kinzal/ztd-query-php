<?php

declare(strict_types=1);

namespace Tests\Unit\Command\View;

use MySqlMemory\Command\View\ViewCommand;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ViewCommand::class)]
#[Small]
final class ViewCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new ViewCommand())->clearsDiagnostics());
    }

    public function testExecuteCreatesAViewThatQueriesRead(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT PRIMARY KEY, b VARCHAR(10))');
        $session->query("INSERT INTO t VALUES (1, 'x'), (2, 'y')");

        $session->query('CREATE VIEW v AS SELECT a, b, a + 1 AS c FROM t');
        $result = $session->query('SELECT c, b FROM v WHERE a > 1')[0];
        self::assertInstanceOf(ResultSet::class, $result);

        self::assertSame([['3', 'y']], $result->rows);
        self::assertSame(['v', 'v', 'd', ''], [$result->columns[1]->table, $result->columns[1]->originalTable, $result->columns[1]->schema, $result->columns[0]->schema]);
    }

    public function testExecuteReportsTheTablesAMaterializedViewReads(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');

        $session->query('CREATE ALGORITHM=TEMPTABLE VIEW v AS SELECT a FROM t');
        $result1 = $session->query('SELECT * FROM v')[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        $column = $result1->columns[0];

        self::assertSame(['v', 't'], [$column->table, $column->originalTable]);
    }

    public function testExecuteRefusesAnExistingName(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');

        $this->expectExceptionCode(1050);
        $this->expectExceptionMessage("Table 't' already exists");

        $session->query('CREATE VIEW t AS SELECT 1');
    }

    public function testExecuteRefusesToReplaceABaseTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');

        $this->expectExceptionCode(1347);
        $this->expectExceptionMessage("'d.t' is not VIEW");

        $session->query('CREATE OR REPLACE VIEW t AS SELECT 1');
    }

    public function testExecuteRefusesToAlterAMissingView(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');

        $this->expectExceptionCode(1146);

        $session->query('ALTER VIEW nope AS SELECT 1');
    }

    public function testExecuteAltersTheQuery(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT)');
        $session->query('CREATE VIEW v AS SELECT a FROM t');

        $session->query('ALTER VIEW v AS SELECT a, b FROM t');

        $result2 = $session->query('SELECT * FROM v')[0];
        self::assertInstanceOf(ResultSet::class, $result2);
        self::assertCount(2, $result2->columns);
    }

    public function testExecuteRefusesATemporaryTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TEMPORARY TABLE tt (a INT)');

        $this->expectExceptionCode(1352);
        $this->expectExceptionMessage("View's SELECT refers to a temporary table 'tt'");

        $session->query('CREATE VIEW v AS SELECT * FROM tt');
    }

    public function testExecuteWarnsThatAQueryCannotBeMerged(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');

        $session->query('CREATE ALGORITHM=MERGE VIEW v AS SELECT DISTINCT a FROM t');

        $schema = $session->instance->dictionary->schema('d');
        self::assertNotNull($schema);
        self::assertSame(['UNDEFINED', [['Warning', 1354, "View merge algorithm can't be used here for now (assumed undefined algorithm)"]]], [$schema->views['v']->algorithm, $session->diagnostics->conditions]);
    }

    public function testExecuteRefusesAViewOfAnInvalidView(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('CREATE VIEW v AS SELECT a FROM t');
        $session->query('DROP TABLE t');

        $this->expectExceptionCode(1356);
        $this->expectExceptionMessage("View 'd.v' references invalid table(s) or column(s) or function(s) or definer/invoker of view lack rights to use them");

        $session->query('CREATE VIEW w AS SELECT * FROM v');
    }
}
