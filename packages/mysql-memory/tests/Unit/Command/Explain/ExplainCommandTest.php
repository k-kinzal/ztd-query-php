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
use PHPUnit\Framework\Attributes\DataProvider;
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
    /**
     * @return iterable<string, array{string, string, string, string, int, string}>
     */
    public static function providerDatabaseScopes(): iterable
    {
        $cases = [
            ['LOCK TABLE current_db.t READ', 'EXPLAIN FOR DATABASE target_db SELECT missing FROM t', 1100, "Table 't' was not locked with LOCK TABLES"],
            ['LOCK TABLE target_db.t READ', 'EXPLAIN FOR DATABASE target_db SELECT missing FROM t', 1054, "Unknown column 'missing' in 'field list'"],
            ['LOCK TABLE target_db.t AS x READ', 'EXPLAIN FOR DATABASE target_db SELECT missing FROM t x', 1054, "Unknown column 'missing' in 'field list'"],
            ['LOCK TABLE target_db.t READ', 'EXPLAIN FOR DATABASE target_db SELECT missing FROM current_db.t', 1100, "Table 't' was not locked with LOCK TABLES"],
            ['', 'EXPLAIN FOR DATABASE target_db TABLE missing', 1146, "Table 'target_db.missing' doesn't exist"],
            ['', 'EXPLAIN FOR DATABASE absent TABLE missing', 1049, "Unknown database 'absent'"],
            ['', 'EXPLAIN FORMAT=wrong FOR DATABASE absent TABLE missing', 1791, "Unknown EXPLAIN format name: 'wrong'"],
        ];
        foreach (['8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $version) {
            foreach (['current_db', ''] as $database) {
                foreach ($cases as [$lock, $sql, $code, $message]) {
                    foreach ([false, true] as $prepared) {
                        yield $version . ':' . $database . ':' . $lock . ':' . $sql . ':' . ($prepared ? 'prepared' : 'direct') => [$version, $database, $lock, $prepared ? "PREPARE p FROM '" . $sql . "'" : $sql, $code, $message];
                    }
                }
            }
        }
    }

    #[DataProvider('providerDatabaseScopes')]
    public function testRestoreDatabaseFollowsPreparationAndExecutionRefusals(string $version, string $database, string $lock, string $sql, int $code, string $message): void
    {
        $session = (new Instance($version, databases: ['current_db', 'target_db']))->connect(database: $database === '' ? null : $database);
        $session->query('CREATE TABLE current_db.t(id INT); CREATE TABLE target_db.t(id INT); ' . $lock);
        $reply = $session->run($sql)[0];

        self::assertInstanceOf(SqlError::class, $reply);
        self::assertSame($code, $reply->getCode());
        self::assertSame($message, $reply->getMessage());
        self::assertSame($database, $session->variables->database);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerDatabaseCollationRestoration(): iterable
    {
        yield 'same schema' => ['EXPLAIN FOR DATABASE current_db SELECT 1', 'latin1_bin'];
        yield 'different schema' => ['EXPLAIN FOR DATABASE target_db SELECT 1', 'utf8mb4_0900_ai_ci'];
        yield 'missing table' => ['EXPLAIN FOR DATABASE target_db TABLE missing', 'utf8mb4_0900_ai_ci'];
        yield 'missing database' => ['EXPLAIN FOR DATABASE absent TABLE missing', 'latin1_bin'];
        yield 'unknown format' => ['EXPLAIN FORMAT=wrong FOR DATABASE target_db TABLE missing', 'latin1_bin'];
        yield 'prepared schema' => ["PREPARE p FROM 'EXPLAIN FOR DATABASE target_db SELECT 1'", 'utf8mb4_0900_ai_ci'];
    }

    #[DataProvider('providerDatabaseCollationRestoration')]
    public function testRestoreDatabaseReloadsCollationOnlyAfterARealDatabaseChange(string $sql, string $collation): void
    {
        $session = (new Instance(databases: ['current_db', 'target_db']))->connect(database: 'current_db');
        $session->query("SET collation_database='latin1_bin'");
        $session->run($sql);

        self::assertSame('current_db', $session->variables->database);
        self::assertSame($collation, $session->variables->read('collation_database'));
    }

    public function testRestoreDatabaseUsesTheServerCollationWithoutACurrentDatabase(): void
    {
        $session = (new Instance(databases: ['target_db']))->connect();
        $session->query("SET collation_database='latin1_bin'; EXPLAIN FOR DATABASE target_db SELECT 1");

        self::assertSame('', $session->variables->database);
        self::assertSame('utf8mb4_0900_ai_ci', $session->variables->read('collation_database'));
    }

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

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(6006);
        $this->expectExceptionMessage('EXPLAIN INTO does not support implicit FORMAT.');

        $session->query('EXPLAIN INTO @v SELECT 1');
    }

    public function testCheckRefusesAnUnknownSchemaBeforeAMissingTable(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1049);
        $this->expectExceptionMessage("Unknown database 'nodb'");

        $session->query('EXPLAIN FOR SCHEMA nodb SELECT * FROM nosuch');
    }

    public function testCheckRefusesAnalyzeForAConnection(): void
    {
        $session = (new Instance())->connect();
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1235);
        $this->expectExceptionMessage("This version of MySQL doesn't yet support 'EXPLAIN ANALYZE FOR CONNECTION'");

        $session->query('EXPLAIN ANALYZE FORMAT=TRADITIONAL FOR CONNECTION 0');
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

    public function testNoteRemainsAbsentForPlainMySql56Explanation(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $session->query('EXPLAIN SELECT 1');
        $warnings = $session->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([], $warnings->rows);
    }

    #[DataProvider('providerLegacyJsonPlans')]
    public function testExecuteNestsTheLegacyNoTableMessage(string $sql): void
    {
        $session = (new Instance('5.6.51', databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(id INT)');
        $result = $session->query($sql)[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([["{\n  \"query_block\": {\n    \"select_id\": 1,\n    \"table\": {\n      \"message\": \"No tables used\"\n    }\n  }\n}"]], $result->rows);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerLegacyJsonPlans(): iterable
    {
        foreach (['SELECT 1', 'INSERT INTO t VALUES()', 'REPLACE t VALUES()', 'INSERT INTO t SET id=1', 'INSERT INTO t SELECT 1'] as $body) {
            yield $body => ['EXPLAIN FORMAT=JSON ' . $body];
        }
    }

    public function testNoteIncludesTheMySql56JsonProjection(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $session->query('EXPLAIN FORMAT=JSON SELECT 1');

        self::assertSame([['Note', 1003, '/* select#1 */ select 1 AS `1`']], $session->diagnostics->conditions);
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

    public function testConnectionRefusesAClosedSessionEvenWhileReferenced(): void
    {
        $instance = new Instance();
        $other = $instance->connect();
        $session = $instance->connect();
        $other->close();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1094);
        $this->expectExceptionMessage('Unknown thread id: 1');

        $session->query('DESC FOR CONNECTION 1');
    }

    public function testConnectionRefusesADestroyedSession(): void
    {
        $instance = new Instance();
        $instance->connect();
        $session = $instance->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1094);

        $session->query('EXPLAIN FOR CONNECTION 1');
    }

    public function testConnectionAnswersNothingForAnIdleConnection(): void
    {
        $instance = new Instance();
        $session = $instance->connect();
        $idle = $instance->connect();
        $own = $session->analyze('EXPLAIN FOR CONNECTION ' . $session->id);
        $other = $session->analyze('EXPLAIN FOR CONNECTION ' . $idle->id);
        $context = new Context(new SqlModes([]), $session->diagnostics, $session->variables, 0.0);

        self::assertNotSame($session->id, $idle->id);
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
        $statement = $session->analyze('EXPLAIN SELECT * FROM t AS x, d.u');

        self::assertInstanceOf(Explain::class, $statement);
        self::assertSame([['x', 'd', 't'], ['u', 'd', 'u']], (new ExplainCommand())->tables($statement->statement, 'd'));
    }

    public function testRowsScanEachTableWhole(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE e1 (a INT PRIMARY KEY, b INT); INSERT INTO e1 VALUES (1,1),(2,2)');
        $delete = $session->analyze('EXPLAIN DELETE FROM e1 WHERE b = 1');
        $insert = $session->analyze('EXPLAIN INSERT INTO e1 VALUES (3,3)');

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
    public function testImpossibleDistinguishesFalseConstantsFromRowPredicates(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(a INT); INSERT INTO t VALUES(1),(2)');
        $context = new Context(new SqlModes([]), $session->diagnostics, $session->variables, 0.0);
        $connection = new \MySqlMemory\Evaluation\Compile\Connection($session->variables, $context);
        $command = new ExplainCommand();

        self::assertTrue($command->impossible($session->analyze('EXPLAIN DELETE t FROM t WHERE 1=2'), $session, $context, $connection));
        self::assertFalse($command->impossible($session->analyze('EXPLAIN DELETE FROM t WHERE a=0'), $session, $context, $connection));
        self::assertFalse($command->impossible($session->analyze('EXPLAIN DELETE FROM t WHERE 1'), $session, $context, $connection));
        self::assertFalse($command->impossible($session->analyze('EXPLAIN DELETE FROM t'), $session, $context, $connection));
        self::assertFalse($command->impossible($session->analyze('SELECT 1'), $session, $context, $connection));
        $reply = $session->query("EXPLAIN DELETE t FROM t WHERE N'0'")[0];
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([['1', 'DELETE', null, null, null, null, null, null, null, null, null, 'Impossible WHERE']], $reply->rows);
        $warnings = $session->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame('delete `d`.`t` from `d`.`t` where false', $warnings->rows[1][2]);
        $rows = $session->query('SELECT a FROM t ORDER BY a')[0];
        self::assertInstanceOf(ResultSet::class, $rows);
        self::assertSame([['1'], ['2']], $rows->rows);
    }

    public function testDeletedNamesBaseTargetsAndPreservesAliases(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(a INT)');
        $statement = $session->analyze('EXPLAIN DELETE LOW_PRIORITY IGNORE x FROM t AS x WHERE 0');
        self::assertInstanceOf(Explain::class, $statement);

        self::assertSame('delete low_priority ignore `d`.`t` from `d`.`t` `x` where false', (new ExplainCommand())->deleted($statement, 'd'));
    }

    public function testKindIdentifiesMultipleDeleteInATablePlan(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(a INT)');
        $explain = $session->analyze('EXPLAIN DELETE t FROM t WHERE a>0');
        self::assertInstanceOf(Explain::class, $explain);
        $command = new ExplainCommand();

        self::assertSame('DELETE', $command->kind($explain->statement));
        self::assertSame([[1, 'DELETE', 't', null, 'ALL', null, null, null, null, 1, '100.00', 'Using where']], $command->rows($explain, $session, 'd'));
    }

    public function testAnalysisPreparesTheExplainedColumnsBeforeCheckingTheReportFormat(): void
    {
        $session = (new Instance())->connect();
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1054);
        $session->query('EXPLAIN ANALYZE FORMAT=JSON SELECT missing');
    }

}
