<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Admin;

use MySqlMemory\Command\Admin\FlushCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(FlushCommand::class)]
#[Small]
final class FlushCommandTest extends TestCase
{
    public function testOptionsResetsOnlySessionCountersAndRecordsTheFlushClock(): void
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

    public function testExecuteLocksTheTablesForReading(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); FLUSH TABLES t WITH READ LOCK');

        self::assertSame([['d', 't', 't', false]], $session->locks);
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
