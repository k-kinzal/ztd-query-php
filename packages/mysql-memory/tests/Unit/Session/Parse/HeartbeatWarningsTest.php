<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\HeartbeatWarnings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(HeartbeatWarnings::class)]
#[Small]
final class HeartbeatWarningsTest extends TestCase
{
    public function testBeforeKeepsCompletedOccurrencesAndRespectsTheInputBoundary(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $sql = 'CHANGE MASTER TO MASTER_HEARTBEAT_PERIOD=0.0001, MASTER_HEARTBEAT_PERIOD=0, INVALID';
        $warnings = new HeartbeatWarnings();

        self::assertSame([], $warnings->before($sql, 0, $session));
        self::assertSame([1703], array_values(array_map(static fn ($notice): int => $notice->code(), $warnings->before($sql, strlen($sql), $session))));
    }
}
