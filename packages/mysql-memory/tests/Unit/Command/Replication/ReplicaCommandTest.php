<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Replication;

use MySqlMemory\Command\Replication\ReplicaCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Registry\Registry;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\Reset;

#[CoversClass(ReplicaCommand::class)]
#[Small]
final class ReplicaCommandTest extends TestCase
{
    #[DataProvider('providerLockedCommands')]
    public function testExecuteRefusesExplicitTableLocksBeforeReplicationState(string $version, string $table, string $mode, string $sql): void
    {
        $session = (new Instance($version, databases: ['test']))->connect(database: 'test');
        $session->query($table);
        $session->query('LOCK TABLES t ' . $mode);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1192);
        $session->query($sql);
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function providerLockedCommands(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $version) {
            $replica = str_starts_with($version, '5.') ? 'SLAVE' : 'REPLICA';
            $commands = ['STOP ' . $replica, 'STOP ' . $replica . ' IO_THREAD', 'STOP ' . $replica . ' SQL_THREAD'];
            if ($version !== '5.6.51') {
                $commands[] = "STOP $replica FOR CHANNEL 'missing'";
                $commands[] = 'START GROUP_REPLICATION';
                $commands[] = 'STOP GROUP_REPLICATION';
            }
            if (!str_starts_with($version, '5.')) {
                $commands[] = version_compare($version, '8.4.0', '<') ? 'RESET MASTER' : 'RESET BINARY LOGS AND GTIDS';
            }
            foreach (['persistent' => 'CREATE TABLE t(id INT)', 'temporary' => 'CREATE TEMPORARY TABLE t(id INT)'] as $kind => $table) {
                foreach (['READ', 'WRITE'] as $mode) {
                    foreach ($commands as $sql) {
                        yield $version . '/' . $kind . '/' . $mode . '/' . $sql => [$version, $table, $mode, $sql];
                    }
                }
            }
        }
    }

    public function testExecuteStopsTheReplicaAfterCommittingAnOpenTransaction(): void
    {
        $session = (new Instance())->connect();
        $session->query('CHANGE REPLICATION SOURCE TO SOURCE_CONNECT_RETRY=1');
        $session->query('START REPLICA SQL_THREAD');
        $session->query('START TRANSACTION');
        $session->query('STOP REPLICA');

        self::assertFalse($session->transaction->open);
        self::assertFalse($session->instance->registry->replication->applying);
    }

    public function testExecuteDoesNotStopTheApplierWhenTableLocksRefuseTheCommand(): void
    {
        $session = (new Instance(databases: ['test']))->connect(database: 'test');
        $session->query('CHANGE REPLICATION SOURCE TO SOURCE_CONNECT_RETRY=1');
        $session->query('START REPLICA SQL_THREAD');
        $session->query('CREATE TABLE t(id INT)');
        $session->query('LOCK TABLES t WRITE');
        try {
            $session->query('STOP REPLICA SQL_THREAD');
            self::fail('Explicit table locks must prevent stopping the applier.');
        } catch (SqlError $error) {
            self::assertSame(1192, $error->getCode());
        }
        self::assertTrue($session->instance->registry->replication->applying);
    }

    public function testStartChannelRequiresAHostForTheReceiverSynonym(): void
    {
        $session = (new Instance())->connect();
        $this->expectExceptionCode(1200);
        $session->query('START REPLICA RELAY_THREAD');
    }

    public function testStopRetainsTheApplierWhenOnlyTheReceiverSynonymIsSelected(): void
    {
        $session = (new Instance())->connect();
        $session->query("CHANGE REPLICATION SOURCE TO SOURCE_HOST='host', SOURCE_USER='user'");
        $session->query('START REPLICA RELAY_THREAD');
        self::assertFalse($session->instance->registry->replication->applying);

        $session->query('START REPLICA SQL_THREAD');
        $session->query('STOP REPLICA RELAY_THREAD');
        self::assertTrue($session->instance->registry->replication->applying);
        $session->query('STOP REPLICA SQL_THREAD');
        self::assertFalse($session->instance->registry->replication->applying);
    }

    #[DataProvider('providerFilterRunning')]
    public function testFilterRefusesTheRunningApplierOfAConfiguredChannel(string $version, string $setup, string $filter, int $code): void
    {
        $session = (new Instance($version, ['server_id' => 1]))->connect();
        $session->query($setup);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode($code);
        $session->query($filter);
    }

    /**
     * @return iterable<string, array{string, string, string, int}>
     */
    public static function providerFilterRunning(): iterable
    {
        foreach (['5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $version) {
            $legacy = $version === '5.7.44';
            $setup = $legacy ? "CHANGE MASTER TO MASTER_HOST='source'; START SLAVE SQL_THREAD" : "CHANGE REPLICATION SOURCE TO SOURCE_HOST='source'; START REPLICA SQL_THREAD";
            yield $version => [$version, $setup, 'CHANGE REPLICATION FILTER REPLICATE_IGNORE_TABLE=()', $legacy ? 3017 : 3085];
            if (!$legacy) {
                yield $version . ' named' => [$version, "CHANGE REPLICATION SOURCE TO SOURCE_HOST='source' FOR CHANNEL 'B'; START REPLICA SQL_THREAD FOR CHANNEL 'b'", "CHANGE REPLICATION FILTER REPLICATE_DO_DB=(a) FOR CHANNEL 'B'", 3085];
                yield $version . ' all' => [$version, "CHANGE REPLICATION SOURCE TO SOURCE_HOST='source' FOR CHANNEL 'b'; START REPLICA SQL_THREAD FOR CHANNEL 'b'", 'CHANGE REPLICATION FILTER REPLICATE_DO_DB=(a)', 3085];
            }
        }
        yield 'legacy without host' => ['5.7.44', 'CHANGE MASTER TO MASTER_HEARTBEAT_PERIOD=0; START SLAVE SQL_THREAD', 'CHANGE REPLICATION FILTER REPLICATE_DO_DB=(a)', 3017];
    }

    public function testFilterSkipsUnconfiguredAppliersForAnUnqualifiedModernRequest(): void
    {
        $session = (new Instance())->connect();
        $session->query('CHANGE REPLICATION SOURCE TO SOURCE_HEARTBEAT_PERIOD=0; START REPLICA SQL_THREAD');
        $reply = $session->query('CHANGE REPLICATION FILTER REPLICATE_IGNORE_TABLE=()')[0];

        self::assertInstanceOf(\MySqlMemory\Result\Completion::class, $reply);
        self::assertTrue($session->instance->registry->replication->applying);
    }

    public function testFilterOnlyChecksTheRequestedChannel(): void
    {
        $session = (new Instance())->connect();
        $session->query("CHANGE REPLICATION SOURCE TO SOURCE_HOST='source'; CHANGE REPLICATION SOURCE TO SOURCE_HOST='source' FOR CHANNEL 'b'; START REPLICA SQL_THREAD FOR CHANNEL 'b'");
        $reply = $session->query("CHANGE REPLICATION FILTER REPLICATE_IGNORE_TABLE=() FOR CHANNEL ''")[0];

        self::assertInstanceOf(\MySqlMemory\Result\Completion::class, $reply);
        self::assertTrue($session->instance->registry->channels['b']->applying);
    }

    public function testFilterAcceptsAStoppedConfiguredChannel(): void
    {
        $session = (new Instance())->connect();
        $session->query("CHANGE REPLICATION SOURCE TO SOURCE_HOST='source' FOR CHANNEL 'B'; START REPLICA SQL_THREAD FOR CHANNEL 'b'; STOP REPLICA SQL_THREAD FOR CHANNEL 'b'");
        $reply = $session->query("CHANGE REPLICATION FILTER REPLICATE_IGNORE_TABLE=() FOR CHANNEL 'B'")[0];

        self::assertInstanceOf(\MySqlMemory\Result\Completion::class, $reply);
    }

    public function testFilterRefusesGroupReplicationChannelsBeforeLookingUpConfiguration(): void
    {
        $session = (new Instance())->connect();
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3139);
        $session->query("CHANGE REPLICATION FILTER REPLICATE_IGNORE_TABLE=() FOR CHANNEL 'group_replication_applier'");
    }

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
        $statement = $session->analyze('RESET BINARY LOGS AND GTIDS TO 06344632');
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

    public function testExecuteRetainsSemanticWarningsOfTheLegacySpellingsOfMySql80(): void
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

    public function testExecuteLeavesTheCurrentSpellingsAndMySql57Alone(): void
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

    public function testTargetsSelectsIndependentNamedRepositories(): void
    {
        $session = (new Instance())->connect();
        $session->query("CHANGE REPLICATION SOURCE TO GET_SOURCE_PUBLIC_KEY=0 FOR CHANNEL 'alpha'");
        $registry = $session->instance->registry;
        $command = new ReplicaCommand();
        self::assertSame(['', 'alpha'], array_keys($command->targets(null, $registry)));
        self::assertSame(['alpha' => $registry->channels['alpha']], $command->targets(new Text('ALPHA'), $registry));
        self::assertFalse($registry->replication->initialized);
    }

    public function testStartChannelPreservesASeparateDefaultApplier(): void
    {
        $session = (new Instance())->connect();
        $session->query("CHANGE REPLICATION SOURCE TO GET_SOURCE_PUBLIC_KEY=0 FOR CHANNEL 'alpha'");
        $session->query("START REPLICA SQL_THREAD FOR CHANNEL 'alpha'");
        self::assertTrue($session->instance->registry->channels['alpha']->applying);
        self::assertFalse($session->instance->registry->replication->applying);
    }

    public function testStopSuppressesAlreadyStoppedNotesOnlyForAChannelSet(): void
    {
        $session = (new Instance())->connect();
        $session->query("CHANGE REPLICATION SOURCE TO GET_SOURCE_PUBLIC_KEY=0 FOR CHANNEL 'alpha'");
        $session->query('STOP REPLICA');
        self::assertSame([], $session->diagnostics->conditions);
        $session->query("STOP REPLICA FOR CHANNEL 'alpha'");
        self::assertSame([['Note', 3084, "Replication thread(s) for channel 'alpha' are already stopped."]], $session->diagnostics->conditions);
    }

    public function testTargetsPreservesNumericNamesThroughUnqualifiedReset(): void
    {
        $session = (new Instance())->connect();
        array_map(static function (string $name) use ($session): void {
            $session->query("CHANGE REPLICATION SOURCE TO GET_SOURCE_PUBLIC_KEY=0 FOR CHANNEL '" . $name . "'");
            $session->query("START REPLICA SQL_THREAD FOR CHANNEL '" . $name . "'");
        }, ['9', '01', '0']);
        $registry = $session->instance->registry;
        self::assertSame(['', 0, '01', 9], array_keys((new ReplicaCommand())->targets(null, $registry)));
        self::assertTrue($registry->channels[9]->applying);
        $session->query("STOP REPLICA FOR CHANNEL '9'; STOP REPLICA FOR CHANNEL '01'; STOP REPLICA FOR CHANNEL '0'");
        $session->query('RESET REPLICA ALL');
        self::assertSame([], $registry->channels);
        self::assertFalse($registry->replication->applying);
    }

    public function testThreadsKeepsUnconfiguredHostsForExplicitApplierCommands(): void
    {
        $session = (new Instance())->connect();
        $session->query("CHANGE REPLICATION SOURCE TO GET_SOURCE_PUBLIC_KEY=0 FOR CHANNEL 'a'");
        $session->query("START REPLICA SQL_THREAD FOR CHANNEL 'a'");
        $session->query('STOP REPLICA');
        self::assertTrue($session->instance->registry->channels['a']->applying);
        $session->query("STOP REPLICA FOR CHANNEL 'a'");
        self::assertFalse($session->instance->registry->channels['a']->applying);
    }

}
