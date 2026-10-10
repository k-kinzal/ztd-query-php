<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Replication;

use MySqlMemory\Command\Replication\ReplicaCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Registry\Registry;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\Reset;

#[CoversClass(ReplicaCommand::class)]
#[Small]
final class ReplicaCommandTest extends TestCase
{
    public function testExecuteRetainsTheGtidTableOpenedByALogReset(): void
    {
        $session = (new Instance())->connect();
        $session->query('FLUSH LOCAL TABLES; RESET BINARY LOGS AND GTIDS');

        self::assertSame([['mysql', 'gtid_executed']], $session->instance->dictionary->cache->names());
        self::assertSame(2, $session->instance->registry->status->read('Flush_commands'));
    }

    public function testResetTargetsCountsOneTableFlushForRepeatedBinaryLogResets(): void
    {
        $session = (new Instance('8.0.44'))->connect();
        $session->query('RESET MASTER, MASTER');

        self::assertSame(1, $session->instance->registry->status->read('Flush_commands'));
    }

    public function testResetTargetsDoesNotCountAFlushWhenResetMasterRequiresDisabledBinaryLogging(): void
    {
        $session = (new Instance('5.6.51', globals: ['log_bin' => 'OFF']))->connect();
        $replies = $session->run('RESET MASTER');

        self::assertInstanceOf(SqlError::class, $replies[0]);
        self::assertSame(1186, $replies[0]->getCode());
        self::assertSame(0, $session->instance->registry->status->read('Flush_commands'));
    }

    public function testResetTargetsDoesNotCountADisabledBinaryLogWhenMySql57StillAcceptsTheReset(): void
    {
        $session = (new Instance('5.7.44', globals: ['log_bin' => 'OFF']))->connect();
        $session->query('RESET MASTER, MASTER');

        self::assertSame(0, $session->instance->registry->status->read('Flush_commands'));
    }

    public function testResetTargetsRetainsTheBinaryLogFlushWhenTheFollowingReplicaResetFails(): void
    {
        $session = (new Instance('8.0.44'))->connect();
        $replies = $session->run("RESET MASTER, REPLICA FOR CHANNEL 'missing'");

        self::assertInstanceOf(SqlError::class, $replies[0]);
        self::assertSame(3074, $replies[0]->getCode());
        self::assertSame(1, $session->instance->registry->status->read('Flush_commands'));
    }

    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new ReplicaCommand())->clearsDiagnostics());
    }

    public function testExecuteCannotStartTheReceiverThread(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1200);
        $this->expectExceptionMessage('The server is not configured as replica; fix in config file or with CHANGE REPLICATION SOURCE TO');

        $session->query('START REPLICA');
    }

    public function testExecuteNotesAPlainTextPasswordFirst(): void
    {
        $session = (new Instance())->connect();
        $session->run("START REPLICA USER = 'u' PASSWORD = 'p'");
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Note', '1759', 'Sending passwords in plain text without SSL/TLS is extremely insecure.'], ['Error', '1200', 'The server is not configured as replica; fix in config file or with CHANGE REPLICATION SOURCE TO']], $warnings->rows);
    }

    public function testStartNotesAUserEvenWithoutAPassword(): void
    {
        $session = (new Instance())->connect();
        $session->run("START REPLICA USER = 'u'");

        self::assertSame(['Note', 1759, 'Sending passwords in plain text without SSL/TLS is extremely insecure.'], $session->diagnostics->conditions[0]);
    }

    public function testExecuteRunsTheApplierThreadUntilStopReplica(): void
    {
        $instance = new Instance();
        $session = $instance->connect();
        $session->query('CHANGE REPLICATION SOURCE TO SOURCE_HEARTBEAT_PERIOD=0');
        $session->query('START REPLICA SQL_THREAD');
        $running = $instance->registry->replication->applying;
        $session->query('STOP REPLICA');
        $session->query('STOP REPLICA');
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([true, false], [$running, $instance->registry->replication->applying]);
        self::assertSame([['Note', '3084', "Replication thread(s) for channel '' are already stopped."]], $warnings->rows);
    }

    public function testStartRequiresAnInitializedRepository(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1871);
        $this->expectExceptionMessage('Replica failed to initialize connection metadata structure from the repository');

        $session->query('START REPLICA SQL_THREAD');
    }

    public function testResetClosesTheRepositoryUntilAnotherSourceChange(): void
    {
        $instance = new Instance();
        $session = $instance->connect();
        $session->query("CHANGE REPLICATION SOURCE TO SOURCE_USER='u'");
        $session->query('RESET REPLICA');
        $error = $session->run('START REPLICA SQL_THREAD')[0];
        $session->query('CHANGE REPLICATION SOURCE TO SOURCE_HEARTBEAT_PERIOD=0');
        $session->query('START REPLICA SQL_THREAD');

        self::assertInstanceOf(SqlError::class, $error);
        self::assertSame(1871, $error->getCode());
        self::assertTrue($instance->registry->replication->applying);
    }

    public function testExecuteRefusesAFilterForAChannel(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1794);

        $session->query("CHANGE REPLICATION FILTER REPLICATE_DO_DB = (a) FOR CHANNEL ''");
    }

    public function testExecuteRefusesGroupReplicationInsideATransaction(): void
    {
        $session = (new Instance())->connect();
        $session->query('BEGIN');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1192);

        $session->query('START GROUP_REPLICATION');
    }

    public function testExecuteFindsGroupReplicationUnconfigured(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3092);
        $this->expectExceptionMessage('The server is not configured properly to be an active member of the group. Please see more details on error log.');

        $session->query('STOP GROUP_REPLICATION');
    }

    public function testResetStartsTheBinaryLogAgain(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('RESET BINARY LOGS AND GTIDS TO 06344632')->statement;
        self::assertInstanceOf(Reset::class, $statement);
        $registry = new Registry();
        (new ReplicaCommand())->reset($statement->targets[0], $registry);

        self::assertSame('binlog.6344632', $registry->binaryLog->active());
    }

    public function testResetRefusesAReplicaWhoseApplierRuns(): void
    {
        $session = (new Instance())->connect();
        $session->query('CHANGE REPLICATION SOURCE TO SOURCE_HEARTBEAT_PERIOD=0');
        $session->query('START REPLICA SQL_THREAD');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3081);
        $this->expectExceptionMessage("This operation cannot be performed with running replication threads; run STOP REPLICA FOR CHANNEL '' first");

        $session->query('RESET REPLICA ALL');
    }

    public function testChannelNamesAMissingChannelInLowerCase(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3074);
        $this->expectExceptionMessage("Replica channel 'x>_q,8[utow' does not exist.");

        (new ReplicaCommand())->channel(new Text('x>_q,8[uTow'));
    }

    public function testChannelRefusesALineFeed(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1525);
        $this->expectExceptionMessage("Incorrect argument contains not-allowed LF value: 'a\nb'");

        $session->query("SHOW REPLICA STATUS FOR CHANNEL 'a\nb'");
    }

    public function testDeprecatedWarnsOfTheLegacySpellingsOfMySql80(): void
    {
        $session = (new Instance('8.0.44'))->connect();
        $session->query('CHANGE MASTER TO MASTER_HOST = \'h\', SOURCE_PORT = 3307, MASTER_LOG_POS = 4');
        $changed = $session->query('SHOW WARNINGS')[0];
        $session->run('RESET SLAVE FOR CHANNEL \'c\'');
        $reset = $session->query('SHOW WARNINGS')[0];
        $session->query('STOP SLAVE');
        $stopped = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $changed);
        self::assertInstanceOf(ResultSet::class, $reset);
        self::assertInstanceOf(ResultSet::class, $stopped);
        self::assertSame(["'CHANGE MASTER' is deprecated and will be removed in a future release. Please use CHANGE REPLICATION SOURCE instead", "'MASTER_HOST' is deprecated and will be removed in a future release. Please use SOURCE_HOST instead", "'MASTER_LOG_POS' is deprecated and will be removed in a future release. Please use SOURCE_LOG_POS instead"], array_column($changed->rows, 2));
        self::assertSame([['Warning', '1287', "'RESET SLAVE' is deprecated and will be removed in a future release. Please use RESET REPLICA instead"], ['Error', '3074', "Replica channel 'c' does not exist."]], $reset->rows);
        self::assertSame(['Warning', '1287', "'STOP SLAVE' is deprecated and will be removed in a future release. Please use STOP REPLICA instead"], $stopped->rows[0]);
    }

    public function testDeprecatedLeavesTheCurrentSpellingsAndMySql57Alone(): void
    {
        $current = (new Instance('8.0.44'))->connect();
        $current->query('STOP REPLICA');
        $currentWarnings = $current->query('SHOW WARNINGS')[0];
        $legacy = (new Instance('5.7.44'))->connect();
        $legacy->run('STOP SLAVE');
        $legacyWarnings = $legacy->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $currentWarnings);
        self::assertInstanceOf(ResultSet::class, $legacyWarnings);
        self::assertSame(['3084'], array_column($currentWarnings->rows, 1));
        self::assertNotContains('1287', array_column($legacyWarnings->rows, 1));
    }

    public function testExecuteRefusesTheReplicaOfA57ServerWithoutAnId(): void
    {
        $session = (new Instance('5.7.44', ['server_id' => '0']))->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1794);

        $session->query('START SLAVE');
    }

    public function testExecuteRefusesResetMasterWithoutBinaryLoggingInMySql56(): void
    {
        $session = (new Instance('5.6.51'))->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1186);
        $this->expectExceptionMessage('Binlog closed, cannot RESET MASTER');

        $session->query('RESET MASTER');
    }
}
