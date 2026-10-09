<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Explain;

use MySqlMemory\Command\Explain\ExplainCommand;
use MySqlMemory\Command\Show\Heading;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\SqlModes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(ExplainCommand::class)]
#[Small]
final class ExplainCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new ExplainCommand())->clearsDiagnostics());
    }

    public function testCheckRefusesAnUnknownFormatBeforeAMissingTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1791);
        $this->expectExceptionMessage("Unknown EXPLAIN format name: 'xyz'");

        $session->query('EXPLAIN FORMAT=xyz SELECT * FROM nosuch');
    }

    public function testCheckRefusesAnalyzeInAnotherFormat(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1235);
        $this->expectExceptionMessage("This version of MySQL doesn't yet support 'EXPLAIN ANALYZE with JSON format'");

        $session->query('EXPLAIN ANALYZE FORMAT=json SELECT 1');
    }

    public function testCheckRefusesIntoWithoutTheJsonFormat(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('EXPLAIN INTO @v SELECT 1')->statement;

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(6006);
        $this->expectExceptionMessage('EXPLAIN INTO does not support implicit FORMAT.');

        (new ExplainCommand())->check($statement, $session);
    }

    public function testCheckRefusesAnUnknownSchemaBeforeAMissingTable(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1049);
        $this->expectExceptionMessage("Unknown database 'nodb'");

        $session->query('EXPLAIN FOR SCHEMA nodb SELECT * FROM nosuch');
    }

    public function testCheckRefusesIntoAndAnalyzeForAConnection(): void
    {
        $session = (new Instance())->connect();
        $into = $session->analyze('EXPLAIN FORMAT=JSON INTO @v FOR CONNECTION 0')->statement;
        $analyze = $session->analyze('EXPLAIN ANALYZE FORMAT=TRADITIONAL FOR CONNECTION 0')->statement;

        self::assertInstanceOf(ExplainConnection::class, $into);
        self::assertInstanceOf(ExplainConnection::class, $analyze);
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1235);
        $this->expectExceptionMessage("This version of MySQL doesn't yet support 'EXPLAIN ANALYZE FOR CONNECTION'");

        (new ExplainCommand())->check($analyze, $session);
    }

    public function testExecuteExplainsAStatementWithoutTables(): void
    {
        $session = (new Instance())->connect();

        $traditional = $session->query('EXPLAIN SELECT 1')[0];
        $tree = $session->query('EXPLAIN FORMAT=TREE SELECT 1')[0];

        self::assertInstanceOf(ResultSet::class, $traditional);
        self::assertInstanceOf(ResultSet::class, $tree);
        self::assertSame([['1', 'SIMPLE', null, null, null, null, null, null, null, null, null, 'No tables used']], $traditional->rows);
        self::assertSame([["-> Rows fetched before execution  (cost=0..0 rows=1)\n"]], $tree->rows);
        self::assertSame([312, 31], [$tree->columns[0]->length, $tree->columns[0]->decimals]);
    }

    public function testExecuteStoresAJsonPlanInAVariable(): void
    {
        $session = (new Instance())->connect();

        $reply = $session->query('EXPLAIN FORMAT=JSON INTO @v SELECT 1')[0];
        $value = $session->query('SELECT @v')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertInstanceOf(ResultSet::class, $value);
        self::assertSame([["{\n  \"query_block\": {\n    \"select_id\": 1,\n    \"message\": \"No tables used\"\n  }\n}"]], $value->rows);
    }

    public function testConnectionRefusesAConnectionNoSessionHas(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1094);
        $this->expectExceptionMessage('Unknown thread id: 0');

        $session->query('EXPLAIN FOR CONNECTION 0');
    }

    public function testConnectionAnswersNothingForAnIdleConnection(): void
    {
        $instance = new Instance();
        $session = $instance->connect();
        $instance->connect();
        $own = $session->analyze('EXPLAIN FOR CONNECTION 1')->statement;
        $other = $session->analyze('EXPLAIN FOR CONNECTION 2')->statement;
        $context = new Context(new SqlModes([]), $session->diagnostics, $session->variables, 0.0);

        self::assertInstanceOf(ExplainConnection::class, $own);
        self::assertInstanceOf(ExplainConnection::class, $other);
        self::assertInstanceOf(Completion::class, (new ExplainCommand())->connection($other, $session, $context));
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3012);

        (new ExplainCommand())->connection($own, $session, $context);
    }

    public function testTablesAnswersTheTablesAStatementReads(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); CREATE TABLE u (a INT)');
        $statement = $session->analyze('EXPLAIN SELECT * FROM t AS x, d.u')->statement;

        self::assertInstanceOf(Explain::class, $statement);
        self::assertSame([['x', 'd', 't'], ['u', 'd', 'u']], (new ExplainCommand())->tables($statement->statement, 'd'));
    }

    public function testRowsScanEachTableWhole(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE e1 (a INT PRIMARY KEY, b INT); INSERT INTO e1 VALUES (1,1),(2,2)');
        $delete = $session->analyze('EXPLAIN DELETE FROM e1 WHERE b = 1')->statement;
        $insert = $session->analyze('EXPLAIN INSERT INTO e1 VALUES (3,3)')->statement;

        self::assertInstanceOf(Explain::class, $delete);
        self::assertInstanceOf(Explain::class, $insert);
        self::assertSame([[1, 'DELETE', 'e1', null, 'ALL', null, null, null, null, 2, '100.00', 'Using where']], (new ExplainCommand())->rows($delete, $session, 'd'));
        self::assertSame([[1, 'INSERT', 'e1', null, 'ALL', null, null, null, null, null, null, null]], (new ExplainCommand())->rows($insert, $session, 'd'));
    }

    public function testCountEstimatesAtLeastOneRow(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');

        self::assertSame(1, (new ExplainCommand())->count($session, new QualifiedName(new Name('t')), 'd'));
    }

    public function testTreeWritesATableScanForEachTable(): void
    {
        self::assertSame("-> Table scan on t\n", (new ExplainCommand())->tree([['t', 'd', 't']]));
    }

    public function testDocumentWritesAQueryBlock(): void
    {
        self::assertStringContainsString('"table_name": "t"', (new ExplainCommand())->document([['t', 'd', 't']]));
    }

    public function testHeadingsNameTheColumnsOfATraditionalPlan(): void
    {
        self::assertSame(['id', 'select_type', 'table', 'partitions', 'type', 'possible_keys', 'key', 'key_len', 'ref', 'rows', 'filtered', 'Extra'], array_map(static fn (Heading $heading): string => $heading->name, (new ExplainCommand())->headings()));
    }

    public function testFormatRefusesAnUnknownFormatBeforeTheTablesOfTheStatement(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1791);

        $session->query('EXPLAIN FORMAT=xyz SELECT * FROM t AS a, t AS a');
    }
}
