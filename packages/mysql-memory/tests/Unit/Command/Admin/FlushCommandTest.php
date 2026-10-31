<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Admin;

use MySqlMemory\Command\Admin\FlushCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(FlushCommand::class)]
#[Small]
final class FlushCommandTest extends TestCase
{
    #[DataProvider('providerExplicitTableLocks')]
    public function testTablesChecksMetadataBeforeClosingHandles(string $version, string $create, string $lock, string $sql, int $code, string $fragment): void
    {
        $session = (new Instance($version, databases: ['test']))->connect(database: 'test');
        $session->query($create);
        $session->query('CREATE TABLE u(id INT)');
        $session->query($lock);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode($code);
        $this->expectExceptionMessage($fragment);
        $session->query($sql);
    }

    /**
     * @return iterable<string, array{string, string, string, string, int, string}>
     */
    public static function providerExplicitTableLocks(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $version) {
            foreach (['', 'TEMPORARY '] as $temporary) {
                foreach (['FLUSH TABLES WITH READ LOCK', 'FLUSH TABLES missing WITH READ LOCK', 'FLUSH TABLES missing FOR EXPORT'] as $sql) {
                    yield $version . '/' . $temporary . $sql => [$version, 'CREATE ' . $temporary . 'TABLE t(id INT)', 'LOCK TABLES t READ', $sql, 1192, 'active locked tables'];
                }
            }
            foreach ([
                ['LOCK TABLES t READ', 'FLUSH TABLES t,missing', 1099, "Table 't'"],
                ['LOCK TABLES t READ', 'FLUSH TABLES missing,t', 1100, "Table 'missing'"],
                ['LOCK TABLES u WRITE', 'FLUSH TABLES t', 1100, "Table 't'"],
                ['LOCK TABLES t AS a READ', 'FLUSH TABLES t', 1099, "Table 't'"],
                ['LOCK TABLES t READ,u READ', 'FLUSH TABLES', 1099, "Table 'u'"],
                ['LOCK TABLES u READ,t READ', 'FLUSH TABLES', 1099, "Table 't'"],
                ['LOCK TABLES t READ', 'FLUSH TABLES t,t WITH READ LOCK', 1066, "Not unique table/alias: 't'"],
            ] as [$lock, $sql, $code, $fragment]) {
                yield $version . '/' . $lock . '/' . $sql => [$version, 'CREATE TABLE t(id INT)', $lock, $sql, $code, $fragment];
            }
            yield $version . '/temporary handle' => [$version, 'CREATE TEMPORARY TABLE t(id INT)', 'LOCK TABLES t WRITE', 'FLUSH TABLES t', 1100, "Table 't'"];
        }
    }

    public function testTablesUsesTheStrongestMetadataLockAcrossAliases(): void
    {
        $session = (new Instance(databases: ['test']))->connect(database: 'test');
        $session->query('CREATE TABLE t(id INT)');
        $session->query('LOCK TABLES t READ,t AS a WRITE');
        $locks = $session->locks;
        $session->query('FLUSH TABLES');
        $session->query('FLUSH TABLES t');

        self::assertEquals($locks, $session->locks);
    }

    public function testExecuteDoesNotLockATemporaryOnlyDestination(): void
    {
        $session = (new Instance(databases: ['test']))->connect(database: 'test');
        $session->query('CREATE TEMPORARY TABLE t(id INT)');
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1146);
        $session->query('FLUSH TABLES t FOR EXPORT');
    }

    public function testOptionsLeavesDisabledBinaryLogsClosed(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query('FLUSH BINARY LOGS; FLUSH LOGS');

        self::assertSame([1], $session->instance->registry->binaryLog->files);
        self::assertSame([], $session->instance->registry->binaryLog->closed);
    }

    /**
     * @return iterable<string, array{string, string, list<array{string, int, string}>}>
     */
    public static function providerMissingPrivilegeLocks(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $version) {
            $tables = ['user', 'db', 'tables_priv', 'columns_priv', 'procs_priv', 'proxies_priv'];
            $groups = [['user', 'db', 'proxies_priv'], ['tables_priv', 'columns_priv', 'procs_priv']];
            if (!str_starts_with($version, '5.')) {
                array_push($tables, 'role_edges', 'default_roles', 'global_grants', 'password_history');
                $groups = [$tables];
            }
            foreach (['READ', 'WRITE'] as $mode) {
                for ($count = 0; $count < count($tables); $count++) {
                    $locked = array_slice($tables, 0, $count);
                    $lock = $locked === [] ? 't WRITE' : implode(', ', array_map(static fn (string $table): string => 'mysql.' . $table . ' ' . $mode, $locked));
                    $conditions = [];
                    foreach ($groups as $group) {
                        $missing = array_values(array_diff($group, $locked));
                        if ($missing !== []) {
                            $conditions[] = ['Error', 1100, "Table '" . $missing[0] . "' was not locked with LOCK TABLES"];
                        }
                    }
                    if ($version === '5.6.51') {
                        $conditions[] = ['Error', 1100, "Table 'servers' was not locked with LOCK TABLES"];
                    }
                    $conditions[] = ['Error', 1105, 'Unknown error'];
                    yield $version . ':' . $mode . ':' . $count => [$version, $lock, $conditions];
                }
            }
        }
    }

    /**
     * @param list<array{string, int, string}> $conditions
     */
    #[DataProvider('providerMissingPrivilegeLocks')]
    public function testPrivilegesReportsEachFailedLoadAndStillResetsStatus(string $version, string $lock, array $conditions): void
    {
        $session = (new Instance($version, databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(id INT); LOCK TABLE ' . $lock);
        $reply = $session->run('FLUSH STATUS, PRIVILEGES, PRIVILEGES')[0];

        self::assertInstanceOf(SqlError::class, $reply);
        self::assertSame(1100, $reply->getCode());
        self::assertSame($conditions, $session->diagnostics->conditions);
        self::assertSame(0, $session->instance->registry->status->read('Com_flush', $session->id));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerReadablePrivilegeTables(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $version) {
            $tables = ['user', 'db', 'tables_priv', 'columns_priv', 'procs_priv', 'proxies_priv'];
            if ($version === '5.6.51') {
                $tables[] = 'servers';
            } elseif (!str_starts_with($version, '5.')) {
                array_push($tables, 'role_edges', 'default_roles', 'global_grants', 'password_history');
            }
            foreach (['READ', 'WRITE'] as $mode) {
                yield $version . ':' . $mode => [$version, implode(', ', array_map(static fn (string $table): string => 'mysql.' . $table . ' ' . $mode, $tables))];
            }
        }
    }

    #[DataProvider('providerReadablePrivilegeTables')]
    public function testPrivilegesAcceptsReadLocksOnEveryReloadedTable(string $version, string $lock): void
    {
        $session = (new Instance($version))->connect();
        $session->query('LOCK TABLE ' . $lock);
        $reply = $session->query('FLUSH PRIVILEGES')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame(0, $reply->warnings());
    }

    public function testPrivilegesReportsOnlyTheServerReadFailureAfterSuccessfulLegacyGrantLoads(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $session->query('LOCK TABLE mysql.user READ, mysql.db READ, mysql.proxies_priv READ, mysql.tables_priv READ, mysql.columns_priv READ, mysql.procs_priv READ');
        $session->run('FLUSH PRIVILEGES');

        self::assertSame([['Error', 1100, "Table 'servers' was not locked with LOCK TABLES"]], $session->diagnostics->conditions);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerRepeatedBinaryFlushes(): iterable
    {
        foreach (['BINARY LOGS, BINARY LOGS', 'BINARY LOGS, LOGS', 'LOGS, BINARY LOGS', 'LOGS, LOGS'] as $options) {
            yield $options => [$options];
        }
    }

    #[DataProvider('providerRepeatedBinaryFlushes')]
    public function testOptionsRotatesTheBinaryLogOnceForRepeatedFlags(string $options): void
    {
        $session = (new Instance())->connect();
        $session->query('FLUSH ' . $options);

        self::assertSame('binlog.000002', $session->instance->registry->binaryLog->active());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerFailingFlushOrders(): iterable
    {
        yield 'relay first' => ["RELAY LOGS FOR CHANNEL 'x', PRIVILEGES, STATUS, BINARY LOGS"];
        yield 'status first' => ["STATUS, BINARY LOGS, PRIVILEGES, RELAY LOGS FOR CHANNEL 'x'"];
    }

    #[DataProvider('providerFailingFlushOrders')]
    public function testOptionsPreservesErrorOrderAndContinuesBinaryAndStatusFlushes(string $options): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(id INT); LOCK TABLE t WRITE');
        $reply = $session->run('FLUSH ' . $options)[0];

        self::assertInstanceOf(SqlError::class, $reply);
        self::assertSame(1100, $reply->getCode());
        self::assertSame([['Error', 1100, "Table 'user' was not locked with LOCK TABLES"], ['Error', 1105, 'Unknown error'], ['Error', 3074, "Replica channel 'x' does not exist."]], $session->diagnostics->conditions);
        self::assertSame('binlog.000002', $session->instance->registry->binaryLog->active());
        self::assertSame(0, $session->instance->registry->status->read('Com_flush', $session->id));
    }

    public function testRelayUsesTheLastItemIncludingItsAbsentChannel(): void
    {
        $session = (new Instance())->connect();
        $reply = $session->query("FLUSH RELAY LOGS FOR CHANNEL 'x', RELAY LOGS")[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame(0, $reply->warnings());
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("Replica channel 'y' does not exist.");
        $session->query("FLUSH RELAY LOGS FOR CHANNEL 'x', RELAY LOGS FOR CHANNEL 'y'");
    }

    public function testExecuteDoesNotCountRetainedLegacyConditionsAsNewWarnings(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $session->run('SELECT missing');
        $conditions = $session->diagnostics->conditions;
        $reply = $session->query('FLUSH TABLES')[0];
        self::assertSame(0, $reply->warnings());
        self::assertSame($conditions, $session->diagnostics->conditions);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerFlushScopes(): iterable
    {
        yield '5.6 requesting session' => ['5.6.51', 1];
        yield '5.7 all sessions' => ['5.7.44', 0];
        yield '8.4 all sessions' => ['8.4.7', 0];
    }

    #[DataProvider('providerFlushScopes')]
    public function testStatusResetsTheReleaseScopeWithoutLosingGlobalTotals(string $version, int $remaining): void
    {
        $instance = new Instance($version);
        $first = $instance->connect();
        $second = $instance->connect();
        $first->query('DO 1');
        $second->query('DO 2');
        $instance->registry->status->add('Aborted_clients');
        $first->query('FLUSH STATUS');

        self::assertSame(0, $instance->registry->status->read('Aborted_clients'));
        self::assertSame([2, 0, $remaining], [$instance->registry->status->read('Com_do'), $instance->registry->status->read('Com_do', $first->id), $instance->registry->status->read('Com_do', $second->id)]);
    }

    public function testOptionsRetainsGlobalStatementCountsAndRecordsTheFlushClock(): void
    {
        $session = (new Instance())->connect();
        $session->query('SELECT 1; FLUSH STATUS');

        self::assertSame(0, $session->instance->registry->status->read('Com_select', $session->id));
        self::assertSame(1, $session->instance->registry->status->read('Com_select'));
        self::assertNotNull($session->instance->registry->status->flushedAt);
    }

    public function testTablesClosesHandlesWithoutRemovingDefinitionsOrRows(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(id INT); INSERT INTO t VALUES(1); FLUSH TABLES');
        $closed = $session->query('SHOW OPEN TABLES FROM d')[0];
        $read = $session->query('SELECT * FROM t')[0];
        $reopened = $session->query('SHOW OPEN TABLES FROM d')[0];

        self::assertInstanceOf(ResultSet::class, $closed);
        self::assertInstanceOf(ResultSet::class, $read);
        self::assertInstanceOf(ResultSet::class, $reopened);
        self::assertSame([], $closed->rows);
        self::assertSame([['1']], $read->rows);
        self::assertSame([['d', 't', '0', '0']], $reopened->rows);
        self::assertSame(1, $session->instance->registry->status->read('Flush_commands'));
    }

    public function testTablesKeepsTheGlobalFlushTotalForNamedTablesAndStatusResets(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(a INT); FLUSH TABLES; FLUSH TABLES t; FLUSH STATUS; FLUSH PRIVILEGES');

        self::assertSame(1, $session->instance->registry->status->read('Flush_commands'));
    }

    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new FlushCommand())->clearsDiagnostics());
    }

    public function testExecuteFlushesWithoutRows(): void
    {
        $replies = (new Instance())->connect()->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); FLUSH LOCAL ERROR LOGS, GENERAL LOGS, GENERAL LOGS; FLUSH STATUS, PRIVILEGES; FLUSH TABLES t, nope; FLUSH RELAY LOGS');

        self::assertSame([0, 0, 0, 0], array_map(static fn ($reply): int => $reply instanceof Completion ? $reply->affectedRows : -1, array_slice($replies, 3)));
    }

    public function testExecuteRotatesTheBinaryLog(): void
    {
        $instance = new Instance();
        $instance->connect()->query('FLUSH BINARY LOGS; FLUSH LOGS');

        self::assertSame('binlog.000003', $instance->registry->binaryLog->active());
    }

    public function testExecuteRefusesAMissingTableToExport(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1146);
        $this->expectExceptionMessage("Table 'd.name' doesn't exist");

        $session->query('FLUSH NO_WRITE_TO_BINLOG TABLES `name` FOR EXPORT');
    }

    public function testExecuteRefusesAMissingDatabaseToLock(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1049);
        $this->expectExceptionMessage("Unknown database 'nope'");

        $session->query('FLUSH TABLES nope.t WITH READ LOCK');
    }

    #[DataProvider('providerLegacyMissingDatabases')]
    public function testExecuteReportsAMissingTableForAnAbsentLegacyDatabase(string $version, string $suffix): void
    {
        $session = (new Instance($version))->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1146);
        $this->expectExceptionMessage("Table 'nope.t' doesn't exist");

        $session->query('FLUSH TABLES nope.t ' . $suffix);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerLegacyMissingDatabases(): iterable
    {
        foreach (['5.6.51', '5.7.44'] as $version) {
            foreach (['WITH READ LOCK', 'FOR EXPORT'] as $suffix) {
                yield $version . '/' . $suffix => [$version, $suffix];
            }
        }
    }

    public function testExecuteLocksTheTablesForReading(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); FLUSH TABLES t WITH READ LOCK');

        self::assertEquals([new \MySqlMemory\Session\State\TableLock('d', 't', 't', false)], $session->locks);
    }

    public function testExecuteRefusesAnotherReplicationChannel(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3074);
        $this->expectExceptionMessage("Replica channel 'x' does not exist.");

        $session->query("FLUSH RELAY LOGS FOR CHANNEL 'x'");
    }

    public function testExecuteWarnsOnceThatFlushHostsIsDeprecatedInMySql80(): void
    {
        $session = (new Instance('8.0.44'))->connect();
        $session->query('FLUSH HOSTS, STATUS, HOSTS');
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1287', "'FLUSH HOSTS' is deprecated and will be removed in a future release. Please use TRUNCATE TABLE performance_schema.host_cache instead"]], $warnings->rows);
    }
}
