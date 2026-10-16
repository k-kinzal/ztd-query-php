<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Replication;

use MySqlMemory\Command\Replication\SourceRepository;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(SourceRepository::class)]
#[Small]
final class SourceRepositoryTest extends TestCase
{
    public function testUpdateRetainsAndResetsHeartbeatPeriodsWithTheConnection(): void
    {
        $session = (new Instance())->connect();
        $session->query('SET GLOBAL replica_net_timeout=200');
        $session->query('CHANGE REPLICATION SOURCE TO SOURCE_PORT=3306');
        $periods = [$session->instance->registry->replication->heartbeatPeriods];
        $session->query('CHANGE REPLICATION SOURCE TO SOURCE_HEARTBEAT_PERIOD=0.0001');
        $periods[] = $session->instance->registry->replication->heartbeatPeriods;
        $session->query('CHANGE REPLICATION SOURCE TO SOURCE_HEARTBEAT_PERIOD=100');
        $session->query('RESET REPLICA');
        $periods[] = $session->instance->registry->replication->heartbeatPeriods;
        $session->query('RESET REPLICA ALL');

        self::assertSame([['' => 100.0], ['' => 0.0], ['' => 100.0]], $periods);
        self::assertSame([], $session->instance->registry->replication->heartbeatPeriods);
    }

    public function testUpdateRetainsUnsignedPortsAcrossAFailedFlush(): void
    {
        $session = (new Instance())->connect();
        $session->run('CHANGE REPLICATION SOURCE TO SOURCE_PORT=2147483648');
        $failed = $session->instance->registry->replication->port;
        $session->run('CHANGE REPLICATION SOURCE TO SOURCE_PORT=4294967296');
        $preserved = $session->instance->registry->replication->port;
        $session->query('CHANGE REPLICATION SOURCE TO SOURCE_PORT=4294970602');

        self::assertSame([2147483648, 2147483648, 3306], [$failed, $preserved, $session->instance->registry->replication->port]);
    }

    public function testCheckPreservesTheResetFailureAndAllClearsThePort(): void
    {
        $session = (new Instance())->connect();
        $session->run('CHANGE REPLICATION SOURCE TO SOURCE_PORT=2147483648');
        $session->run('RESET REPLICA');
        $reset = array_column($session->diagnostics->conditions, 1);
        $session->query('RESET REPLICA ALL');
        $session->query('CHANGE REPLICATION SOURCE TO SOURCE_PORT=0');

        self::assertSame([1742, 1201, 1105], $reset);
        self::assertSame(3306, $session->instance->registry->replication->port);
    }
    public function testOpenPreventsRepairingSettingsAfterResetInitializationFailed(): void
    {
        $session = (new Instance())->connect();
        $session->run('CHANGE REPLICATION SOURCE TO SOURCE_PORT=2147483648');
        $session->run('RESET REPLICA');
        $session->run('CHANGE REPLICATION SOURCE TO SOURCE_PORT=3306');

        self::assertSame([1742, 1201], array_column($session->diagnostics->conditions, 1));
        self::assertSame(2147483648, $session->instance->registry->replication->port);
    }

    public function testUpdateClampsLongHexadecimalOperandsBeforeNarrowingThePort(): void
    {
        $session = (new Instance())->connect();
        $session->run("CHANGE REPLICATION SOURCE TO SOURCE_PORT=X'9043ac133F7D6F1fd654'");

        self::assertSame(4294967295, $session->instance->registry->replication->port);
        self::assertSame([1742, 1380], array_column($session->diagnostics->conditions, 1));
    }

}
