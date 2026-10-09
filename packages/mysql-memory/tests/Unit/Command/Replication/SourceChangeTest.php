<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Replication;

use MySqlMemory\Command\Replication\SourceChange;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(SourceChange::class)]
#[Small]
final class SourceChangeTest extends TestCase
{
    public function testCheckRequiresAnExistingPrivilegeChecksUser(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3926);
        $this->expectExceptionMessage("PRIVILEGE_CHECKS_USER for replication channel '' was set to `missing`@`%`, but this is not an existing user.");

        $session->query('CHANGE REPLICATION SOURCE TO PRIVILEGE_CHECKS_USER=missing');
    }

    public function testHeartbeatUsesTheGlobalTimeout(): void
    {
        $session = (new Instance())->connect();
        $session->query('SET GLOBAL replica_net_timeout=120');
        $session->query('CHANGE REPLICATION SOURCE TO SOURCE_HEARTBEAT_PERIOD=100');

        self::assertSame([], $session->diagnostics->conditions);
    }

    public function testHeartbeatWarnsAboutSubMillisecondPeriods(): void
    {
        $session = (new Instance())->connect();
        $session->query('CHANGE REPLICATION SOURCE TO SOURCE_HEARTBEAT_PERIOD=0.0001');

        self::assertSame([1703], array_column($session->diagnostics->conditions, 1));
    }

    public function testGtidsRefusesAutoPositionBeforeCredentials(): void
    {
        $session = (new Instance())->connect();
        $answers = $session->run("CHANGE REPLICATION SOURCE TO SOURCE_AUTO_POSITION=1,SOURCE_USER='u',SOURCE_HEARTBEAT_PERIOD=100");

        self::assertInstanceOf(SqlError::class, $answers[0]);
        self::assertSame([1704, 1777], array_column($session->diagnostics->conditions, 1));
    }

    public function testGtidsRequiresOnForAnonymousAssignment(): void
    {
        $session = (new Instance('8.4.7', ['gtid_mode' => 'ON_PERMISSIVE']))->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(4015);

        $session->query('CHANGE REPLICATION SOURCE TO ASSIGN_GTIDS_TO_ANONYMOUS_TRANSACTIONS=LOCAL');
    }

    public function testWarningsPrecedeCompressionFailure(): void
    {
        $session = (new Instance())->connect();
        $session->run("CHANGE REPLICATION SOURCE TO SOURCE_USER='u',SOURCE_LOG_FILE='f',SOURCE_HEARTBEAT_PERIOD=100,SOURCE_COMPRESSION_ALGORITHMS='bad'");

        self::assertSame([1704, 3023, 1759, 1760, 3920], array_column($session->diagnostics->conditions, 1));
    }

    public function testCompressionAcceptsCaseDuplicatesAndTrailingComma(): void
    {
        (new SourceChange())->compression('ZLIB,zlib,zstd,uncompressed,', '');

        self::expectNotToPerformAssertions();
    }

    public function testCompressionNamesTheFirstBadElement(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3920);
        $this->expectExceptionMessage("Invalid SOURCE_COMPRESSION_ALGORITHMS ' zstd' for channel 'channel'.");

        (new SourceChange())->compression('zlib, zstd', 'channel');
    }
}
