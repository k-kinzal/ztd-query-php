<?php

declare(strict_types=1);

namespace Tests\Unit\System\Performance;

use MySqlMemory\Instance;
use MySqlMemory\System\Performance\StatusVariables;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;

#[CoversClass(StatusVariables::class)]
#[Small]
final class StatusVariablesTest extends TestCase
{
    public function testOfReadsTheCatalogOfARelease(): void
    {
        self::assertSame(StatusVariables::of(GrammarRelease::MySql847), StatusVariables::of(GrammarRelease::MySql847));
        self::assertSame(['Aborted_clients', 'Global', '0', true], StatusVariables::of(GrammarRelease::MySql847)->entries[0]);
    }

    public function testValuesAnswersTheValuesOfAScope(): void
    {
        $instance = new Instance();
        $instance->connect();
        $catalog = StatusVariables::of(GrammarRelease::MySql847);

        self::assertSame([['Connections', '1'], ['Threads_connected', '3']], array_values(array_filter($catalog->values($instance, true, false, 3), static fn (array $value): bool => in_array($value[0], ['Connections', 'Threads_connected'], true))));
        self::assertSame([[], [['Com_select', '0']]], [array_values(array_filter($catalog->values($instance, true, false, 3), static fn (array $value): bool => $value[0] === 'Com_select')), array_values(array_filter($catalog->values($instance, false, false, 3, false), static fn (array $value): bool => $value[0] === 'Com_select'))]);
        self::assertSame([], array_values(array_filter($catalog->values($instance, false, true, 3), static fn (array $value): bool => $value[0] === 'Uptime')));
    }

    public function testValuesCountsSimulatedWaits(): void
    {
        $instance = new Instance();
        $session = $instance->connect();
        $catalog = new StatusVariables([['Uptime', 'Global', '0', true], ['Uptime_since_flush_status', 'Global', '0', true]]);
        $before = (int) $catalog->values($instance, true, false, 1)[0][1];
        $session->query('SELECT SLEEP(7); FLUSH STATUS; SELECT SLEEP(3)');
        $after = $catalog->values($instance, true, false, 1);

        self::assertGreaterThanOrEqual($before + 10, (int) $after[0][1]);
        self::assertLessThanOrEqual($before + 11, (int) $after[0][1]);
        self::assertGreaterThanOrEqual(3, (int) $after[1][1]);
        self::assertLessThanOrEqual(4, (int) $after[1][1]);
    }

    public function testValuesReadsTrafficInTheRequestedScope(): void
    {
        $instance = new Instance();
        $instance->registry->status->add('Bytes_received', 1, 13);
        $instance->registry->status->add('Bytes_received', 2, 8);
        $instance->registry->status->add('Bytes_sent', 1, 56);
        $catalog = new StatusVariables([['Bytes_received', 'Both', '0', true], ['Bytes_sent', 'Both', '0', true]]);

        self::assertSame([['Bytes_received', '21'], ['Bytes_sent', '56']], $catalog->values($instance, true, false, 2));
        self::assertSame([['Bytes_received', '8'], ['Bytes_sent', '0']], $catalog->values($instance, false, false, 2, connection: 2));
    }

    public function testValuesWrapsPinnedTimestampsBeforeTheRealOrigins(): void
    {
        $instance = new Instance();
        $instance->started = 1000.75;
        $instance->registry->status->flushedAt = 1003.75;
        $catalog = new StatusVariables([['Uptime', 'Global', '0', true], ['Uptime_since_flush_status', 'Global', '0', true]]);

        self::assertSame([['Uptime', '18446744073709550617'], ['Uptime_since_flush_status', '18446744073709550614']], $catalog->values($instance, true, false, 1, instant: 1.9));
        self::assertSame([['Uptime', '4'], ['Uptime_since_flush_status', '1']], $catalog->values($instance, true, false, 1, instant: 1004.1));
    }

    public function testCounterKeepsOldAndNewReplicationListingsOnOneTotal(): void
    {
        $instance = new Instance('8.0.44');
        $session = $instance->connect();
        $session->query('SHOW SLAVE HOSTS; SHOW REPLICAS; SHOW SLAVE STATUS; SHOW REPLICA STATUS; SHOW MASTER STATUS');
        $catalog = StatusVariables::of(GrammarRelease::MySql8044);
        $values = array_column($catalog->values($instance, false, false, 1, false, $session->id), 1, 0);

        self::assertSame(['2', '2', '2', '2', '1'], [$values['Com_show_replicas'], $values['Com_show_slave_hosts'], $values['Com_show_replica_status'], $values['Com_show_slave_status'], $values['Com_show_master_status']]);
    }

    public function testConnectionsSeparatesCurrentPeakAndTotalAndLocalizesThePeakTime(): void
    {
        $instance = new Instance(globals: ['event_scheduler' => 'OFF']);
        $first = $instance->connect();
        $second = $instance->connect();
        $second->close();
        $instance->registry->threads->maximumAt = 1700000000.0;
        $catalog = new StatusVariables([]);
        $values = $catalog->connections($instance, 1, new \MySqlMemory\Value\Zone('+09:00', 32400));

        self::assertSame(['Connections' => '2', 'Threads_connected' => '1', 'Threads_running' => '1', 'Max_used_connections' => '2', 'Max_used_connections_time' => '2023-11-15 07:13:20'], $values);
        $first->query('SET GLOBAL event_scheduler=ON');
        self::assertSame('2', $catalog->connections($instance, 1, \MySqlMemory\Value\Zone::utc())['Threads_running']);
    }
}
