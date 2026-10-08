<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Maintenance;

use MySqlMemory\Command\Maintenance\AdministrationCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\KeyCache\CacheIndex;
use SqlSemantics\Platform\MySql\Statement\Server\KeyCache\LoadIndex;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\CheckTable;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\RepairTable;

#[CoversClass(AdministrationCommand::class)]
#[Small]
final class AdministrationCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new AdministrationCommand())->clearsDiagnostics());
    }

    public function testExecuteChecksEachTableInWrittenOrder(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');

        $result = $session->query('CHECK TABLE nope, t, abc.q')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([
            ['d.nope', 'check', 'Error', "Table 'd.nope' doesn't exist"],
            ['d.nope', 'check', 'status', 'Operation failed'],
            ['d.t', 'check', 'status', 'OK'],
            ['abc.q', 'check', 'Error', "Unknown database 'abc'"],
            ['abc.q', 'check', 'error', 'Corrupt'],
        ], $result->rows);
        self::assertSame([['Table', 512], ['Op', 40], ['Msg_type', 40], ['Msg_text', 1572864]], array_map(static fn ($column): array => [$column->name, $column->length], $result->columns));
    }

    public function testExecuteAnswersTheNotesOfAnInnoDbTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');

        $optimize = $session->query('OPTIMIZE TABLE t')[0];
        $repair = $session->query('REPAIR TABLE t QUICK')[0];
        $cache = $session->query('CACHE INDEX t IN DEFAULT')[0];
        $load = $session->query('LOAD INDEX INTO CACHE t')[0];

        self::assertInstanceOf(ResultSet::class, $optimize);
        self::assertSame([['d.t', 'optimize', 'note', 'Table does not support optimize, doing recreate + analyze instead'], ['d.t', 'optimize', 'status', 'OK']], $optimize->rows);
        self::assertInstanceOf(ResultSet::class, $repair);
        self::assertSame([['d.t', 'repair', 'note', "The storage engine for the table doesn't support repair"]], $repair->rows);
        self::assertInstanceOf(ResultSet::class, $cache);
        self::assertSame([['d.t', 'assign_to_keycache', 'note', "The storage engine for the table doesn't support assign_to_keycache"]], $cache->rows);
        self::assertInstanceOf(ResultSet::class, $load);
        self::assertSame([['d.t', 'preload_keys', 'note', "The storage engine for the table doesn't support preload_keys"]], $load->rows);
    }

    public function testExecuteRefusesAPartitionOfATableThatIsNotPartitioned(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');

        $result = $session->query('CACHE INDEX t PARTITION (ALL) IN DEFAULT')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['d.t', 'assign_to_keycache', 'Error', 'Partition management on a not partitioned table is not possible'], ['d.t', 'assign_to_keycache', 'status', 'Operation failed']], $result->rows);
    }

    public function testExecuteRefusesAnUnknownKeyCache(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1284);
        $this->expectExceptionMessage("Unknown key cache 'hot'");

        $session->query('CACHE INDEX t IN hot');
    }

    public function testExecuteNeedsADatabaseForAnUnqualifiedTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1046);

        $session->query('CHECK TABLE d.t, t');
    }

    public function testExecuteAnswersOneRowForAHistogramOfSeveralTables(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');

        $result = $session->query('ANALYZE TABLE t, nope DROP HISTOGRAM ON a')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['', 'histogram', 'Error', 'Only one table can be specified while modifying histogram statistics.']], $result->rows);
    }

    public function testExecuteChangesTheHistogramsOfATable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT PRIMARY KEY, b INT)');

        $created = $session->query('ANALYZE TABLE t UPDATE HISTOGRAM ON b, a')[0];
        $missing = $session->query('ANALYZE TABLE nope UPDATE HISTOGRAM ON b')[0];

        self::assertInstanceOf(ResultSet::class, $created);
        self::assertSame([['d.t', 'histogram', 'Error', "The column 'a' is covered by a single-part unique index."], ['d.t', 'histogram', 'status', "Histogram statistics created for column 'b'."]], $created->rows);
        self::assertInstanceOf(ResultSet::class, $missing);
        self::assertSame([['d.nope', 'histogram', 'Error', "Table 'd.nope' doesn't exist"]], $missing->rows);
    }

    public function testNamesAnswersTheTablesAndWhetherTheySelectPartitions(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('LOAD INDEX INTO CACHE t PARTITION (ALL)')->statement;
        self::assertInstanceOf(LoadIndex::class, $statement);

        self::assertSame([['t', true]], array_map(static fn (array $name): array => [$name[0]->name->value, $name[1]], (new AdministrationCommand())->names($statement)));
    }

    public function testOperationAnswersTheOpColumn(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('CACHE INDEX t IN DEFAULT')->statement;
        self::assertInstanceOf(CacheIndex::class, $statement);

        self::assertSame('assign_to_keycache', (new AdministrationCommand())->operation($statement));
    }

    public function testFailureAnswersNullForATableThatExists(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');
        $statement = $session->analyze('CHECK TABLE t')->statement;
        self::assertInstanceOf(CheckTable::class, $statement);

        self::assertNull((new AdministrationCommand())->failure($session, 'd', $statement->tables[0]->name, $session->instance->dictionary->table('d', 't')));
    }

    public function testViewChecksAViewAndRefusesTheOtherOperations(): void
    {
        $command = new AdministrationCommand();

        self::assertSame([
            [['status', 'OK']],
            [['Error', "'d.v' is not BASE TABLE"], ['status', 'Operation failed']],
            [['Error', 'Cannot create histogram statistics for a view.']],
        ], [$command->view('check', 'd.v', false), $command->view('optimize', 'd.v', false), $command->view('analyze', 'd.v', true)]);
    }

    public function testExecuteChecksAView(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); CREATE VIEW v AS SELECT a FROM t');

        $check = $session->query('CHECK TABLE v')[0];
        $repair = $session->query('REPAIR TABLE v')[0];

        self::assertInstanceOf(ResultSet::class, $check);
        self::assertSame([['d.v', 'check', 'status', 'OK']], $check->rows);
        self::assertInstanceOf(ResultSet::class, $repair);
        self::assertSame([['d.v', 'repair', 'Error', "'d.v' is not BASE TABLE"], ['d.v', 'repair', 'status', 'Operation failed']], $repair->rows);
    }

    public function testValidateRefusesAnUnknownKeyCacheBeforeAMissingDatabase(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('CACHE INDEX t IN other')->statement;
        self::assertInstanceOf(CacheIndex::class, $statement);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1284);

        (new AdministrationCommand())->validate($statement, (new AdministrationCommand())->names($statement), $session);
    }

    public function testReportAnswersTheRowsOfAMissingTableAndOfAMissingDatabase(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');
        $statement = $session->analyze('REPAIR TABLE nope, x.nope')->statement;
        self::assertInstanceOf(RepairTable::class, $statement);

        self::assertSame(
            [
                [['d.nope', 'repair', 'Error', "Table 'd.nope' doesn't exist"], ['d.nope', 'repair', 'status', 'Operation failed']],
                [['x.nope', 'repair', 'Error', "Unknown database 'x'"], ['x.nope', 'repair', 'error', 'Corrupt']],
            ],
            [(new AdministrationCommand())->report('repair', null, $statement->tables[0]->name, false, $session), (new AdministrationCommand())->report('repair', null, $statement->tables[1]->name, false, $session)],
        );
    }

    public function testRowsPutsTheLabelAndTheOpColumnBeforeEachMessage(): void
    {
        self::assertSame([['d.t', 'check', 'status', 'OK'], ['d.t', 'check', 'note', 'n']], (new AdministrationCommand())->rows('d.t', 'check', [['status', 'OK'], ['note', 'n']]));
    }

    public function testOutcomeAnswersOkForCheck(): void
    {
        self::assertSame([['status', 'OK']], (new AdministrationCommand())->outcome('check'));
    }

    public function testColumnsAnswersTheColumnsInTheCharacterSetOfTheResults(): void
    {
        $session = (new Instance())->connect();
        $session->query("SET character_set_results = 'latin1'");

        self::assertSame([128, 10, 10, 393216], array_map(static fn ($column): int => $column->length, AdministrationCommand::columns($session)));
    }
}
