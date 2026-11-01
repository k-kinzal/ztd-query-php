<?php

declare(strict_types=1);

namespace Tests\Unit\Registry;

use MySqlMemory\Instance;
use MySqlMemory\Registry\EventScheduler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(EventScheduler::class)]
#[Small]
final class EventSchedulerTest extends TestCase
{
    public function testConfiguredReadsTheStartupSetting(): void
    {
        self::assertNull((new Instance(globals: ['event_scheduler' => 'DISABLED']))->registry->eventScheduler->row());
        self::assertNull((new Instance('5.7.44'))->registry->eventScheduler->row());
        self::assertNotNull((new Instance(globals: ['event_scheduler' => 'ON']))->registry->eventScheduler->row());
    }

    public function testConfigureAllocatesDistinctIdentitiesAcrossRestartsAndClients(): void
    {
        $instance = new Instance();
        $scheduler = $instance->registry->eventScheduler;
        $first = $scheduler->row();
        self::assertNotNull($first);
        $session = $instance->connect();
        $session->query('SET GLOBAL event_scheduler=OFF');
        self::assertNull($scheduler->row());
        $session->query('SET GLOBAL event_scheduler=ON');
        $second = $scheduler->row();
        self::assertNotNull($second);
        self::assertCount(3, array_unique([$first['ID'], $session->id, $second['ID']]));
        self::assertSame(1, $instance->connections());
    }

    public function testActivatedWakesForAnEnabledEventAndKeepsTheWaitAfterItsRemoval(): void
    {
        $instance = new Instance();
        $session = $instance->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE EVENT later ON SCHEDULE AT '2035-01-01' DO DO 1");
        $row = $instance->registry->eventScheduler->row();
        self::assertNotNull($row);
        self::assertSame('Waiting for next activation', $row['STATE']);
        $session->query('DROP EVENT later');
        self::assertSame($row['STATE'], $instance->registry->eventScheduler->row()['STATE'] ?? null);
    }

    public function testStopRemovesTheDaemonOnShutdown(): void
    {
        $instance = new Instance();
        $instance->shutdown();

        self::assertNull($instance->registry->eventScheduler->row());
    }

    public function testRowCountsActualClockTimeIndependentlyOfTheSessionTimestamp(): void
    {
        $instance = new Instance();
        $instance->connect()->query('SET timestamp=2100000000');
        $instance->registry->threads->pass(3);
        $row = $instance->registry->eventScheduler->row();
        self::assertNotNull($row);

        self::assertContains($row['TIME'], [3, 4]);
        self::assertSame(['USER' => 'event_scheduler', 'HOST' => 'localhost', 'DB' => null, 'COMMAND' => 'Daemon', 'STATE' => 'Waiting on empty queue', 'INFO' => null, 'EXECUTION_ENGINE' => 'PRIMARY'], array_diff_key($row, ['ID' => true, 'TIME' => true]));
    }
}
