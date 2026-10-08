<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Replication;

use MySqlMemory\Command\Replication\ReplicationShowCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowBinlogEvents;

#[CoversClass(ReplicationShowCommand::class)]
#[Small]
final class ReplicationShowCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new ReplicationShowCommand())->clearsDiagnostics());
    }

    public function testExecuteListsNoReplicaStatus(): void
    {
        $result = (new Instance())->connect()->query('SHOW REPLICA STATUS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[], 60, ['Replica_IO_State', 56], ['Source_Port', 8], ['Network_Namespace', 772]], [$result->rows, count($result->columns), [$result->columns[0]->name, $result->columns[0]->length], [$result->columns[3]->name, $result->columns[3]->length], [$result->columns[59]->name, $result->columns[59]->length]]);
    }

    public function testExecuteListsTheBinaryLogFiles(): void
    {
        $session = (new Instance())->connect();
        $session->query('FLUSH BINARY LOGS');
        $logs = $session->query('SHOW BINARY LOGS')[0];
        $status = $session->query('SHOW BINARY LOG STATUS')[0];

        self::assertInstanceOf(ResultSet::class, $logs);
        self::assertInstanceOf(ResultSet::class, $status);
        self::assertSame([['binlog.000001', '202', 'No'], ['binlog.000002', '158', 'No']], $logs->rows);
        self::assertSame([['binlog.000002', '158', '', '', '']], $status->rows);
    }

    public function testExecuteListsTheEventsOfTheFirstFile(): void
    {
        $result = (new Instance())->connect()->query('SHOW BINLOG EVENTS LIMIT 00000000000000030817982')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['binlog.000001', '4', 'Format_desc', '1', '127', 'Server ver: 8.4.7, Binlog ver: 4'], ['binlog.000001', '127', 'Previous_gtids', '1', '158', '']], $result->rows);
    }

    public function testExecuteRefusesALogTheIndexLacks(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1220);
        $this->expectExceptionMessage('Error when executing command SHOW BINLOG EVENTS: Could not find target log');

        $session->query("SHOW BINLOG EVENTS IN 'text' FROM 0");
    }

    public function testExecuteListsTheRelayLogOfTheDefaultChannel(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SHOW RELAYLOG EVENTS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[$session->variables->read('relay_log') . '.000001', '4', 'Format_desc', '1', '127', 'Server ver: 8.4.7, Binlog ver: 4'], [$session->variables->read('relay_log') . '.000001', '127', 'Previous_gtids', '1', '158', '']], $result->rows);
    }

    public function testExecuteRefusesAnotherChannelBeforeThePosition(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3074);
        $this->expectExceptionMessage("Replica channel 'a'b' does not exist.");

        $session->query("SHOW RELAYLOG EVENTS FROM 9990851691202704118800000000000000000000 FOR CHANNEL 'a''b'");
    }

    public function testExecuteListsNoReplica(): void
    {
        $result = (new Instance())->connect()->query('SHOW REPLICAS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[], ['Server_Id', 'Host', 'Port', 'Source_Id', 'Replica_UUID']], [$result->rows, array_map(static fn ($column): string => $column->name, $result->columns)]);
    }

    public function testWindowStartsAtTheFirstEventAtOrAfterThePosition(): void
    {
        $events = [['l', 4, 'Format_desc', 1, 127, ''], ['l', 127, 'Previous_gtids', 1, 158, '']];

        self::assertSame([[$events[1]], $events, []], [(new ReplicationShowCommand())->window($events, '5', null, 'C'), (new ReplicationShowCommand())->window($events, '0', null, 'C'), (new ReplicationShowCommand())->window($events, '4294967296', null, 'C')]);
    }

    public function testWindowCannotReadAPositionBeyondTheLastOne(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1220);
        $this->expectExceptionMessage('Error when executing command SHOW BINLOG EVENTS: Error reading Log_event at position 18446744073709551615: Failed decoding event: I/O error reading log event');

        (new ReplicationShowCommand())->window([], '102638451160232604379166524', null, 'SHOW BINLOG EVENTS');
    }

    public function testWindowListsEveryEventForLimitZero(): void
    {
        $session = (new Instance())->connect();
        $all = $session->query('SHOW BINLOG EVENTS LIMIT 0')[0];
        $second = $session->query('SHOW BINLOG EVENTS LIMIT 1, 1')[0];

        self::assertInstanceOf(ResultSet::class, $all);
        self::assertInstanceOf(ResultSet::class, $second);
        self::assertSame([2, '127'], [count($all->rows), $second->rows[0][1] ?? null]);
    }

    public function testLimitRefusesAVariable(): void
    {
        $statement = (new Instance())->connect()->analyze("SHOW BINLOG EVENTS IN 'text' LIMIT x")->statement;
        self::assertInstanceOf(ShowBinlogEvents::class, $statement);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1327);
        $this->expectExceptionMessage('Undeclared variable: x');

        (new ReplicationShowCommand())->limit($statement->limit);
    }

    public function testBoundReadsAnUnsignedInteger(): void
    {
        self::assertSame([null, 30817982, PHP_INT_MAX], [(new ReplicationShowCommand())->bound(null), (new ReplicationShowCommand())->bound(new NumberLiteral('00000000000000030817982')), (new ReplicationShowCommand())->bound(new NumberLiteral('18446744073709551616'))]);
    }

    public function testListingDescribesNumbersAsUnsignedBinaryNumbers(): void
    {
        $session = (new Instance())->connect();
        $result = (new ReplicationShowCommand())->listing([['Pos', Field::LongLong, 12], ['Info', 20]], [[4, 'x']], new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, 0.0));

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[32929, 63], [1, 255]], [[$result->columns[0]->flags, $result->columns[0]->charset], [$result->columns[1]->flags, $result->columns[1]->charset]]);
    }

    public function testExecuteNamesTheColumnsOfShowSlaveStatusAndHostsInTheLegacyVocabularyAndWarns(): void
    {
        $session = (new Instance('8.0.44'))->connect();
        $status = $session->query('SHOW SLAVE STATUS')[0];
        $warnings = $session->query('SHOW WARNINGS')[0];
        $hosts = $session->query('SHOW SLAVE HOSTS')[0];

        self::assertInstanceOf(ResultSet::class, $status);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertInstanceOf(ResultSet::class, $hosts);
        self::assertSame(['Slave_IO_State', 'Master_Host', 'Replicate_Do_DB', 'Get_master_public_key'], [$status->columns[0]->name, $status->columns[1]->name, $status->columns[12]->name, $status->columns[58]->name]);
        self::assertSame(['Server_id', 'Host', 'Port', 'Master_id', 'Slave_UUID'], array_map(static fn ($column): string => $column->name, $hosts->columns));
        self::assertSame([['Warning', '1287', "'SHOW SLAVE STATUS' is deprecated and will be removed in a future release. Please use SHOW REPLICA STATUS instead"]], $warnings->rows);
    }

    public function testExecuteListsTheShorterFormatDescriptionOfMySql80(): void
    {
        $result = (new Instance('8.0.44'))->connect()->query('SHOW BINLOG EVENTS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['binlog.000001', '4', 'Format_desc', '1', '126', 'Server ver: 8.0.44, Binlog ver: 4'], ['binlog.000001', '126', 'Previous_gtids', '1', '157', '']], $result->rows);
    }

    public function testLegacyNamesColumnsWithMasterAndSlave(): void
    {
        self::assertSame([['Read_Master_Log_Pos', Field::LongLong, 11], ['Replicate_Do_DB', 20], ['Server_id', Field::Long, 11]], (new ReplicationShowCommand())->legacy([['Read_Source_Log_Pos', Field::LongLong, 11], ['Replicate_Do_DB', 20], ['Server_Id', Field::Long, 11]]));
    }

    public function testExecuteRefusesTheRelayLogOfA57ServerWithoutAnId(): void
    {
        $session = (new Instance('5.7.44', ['server_id' => '0']))->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1794);

        $session->query('SHOW RELAYLOG EVENTS');
    }
}
