<?php

declare(strict_types=1);

namespace Tests\Unit\Variable;

use MySqlMemory\Instance;
use MySqlMemory\Variable\ReplicationSettings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReplicationSettings::class)]
#[Small]
final class ReplicationSettingsTest extends TestCase
{
    public function testTimeoutWarnsForEveryChannelWhoseHeartbeatExceedsTheTimeout(): void
    {
        $session = (new Instance())->connect();
        $session->query("CHANGE REPLICATION SOURCE TO SOURCE_HEARTBEAT_PERIOD=100 FOR CHANNEL 'one'");
        $session->query("CHANGE REPLICATION SOURCE TO SOURCE_HEARTBEAT_PERIOD=100 FOR CHANNEL 'two'");
        $session->query('SET GLOBAL replica_net_timeout=99');
        $warnings = array_column($session->diagnostics->conditions, 1);
        $session->query('SET GLOBAL replica_net_timeout=100');

        self::assertSame([1704, 1704], $warnings);
        self::assertSame([], $session->diagnostics->conditions);
    }

}
