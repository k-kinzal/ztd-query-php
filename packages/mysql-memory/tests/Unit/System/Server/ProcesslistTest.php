<?php

declare(strict_types=1);

namespace Tests\Unit\System\Server;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\System\Server\Processlist;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Processlist::class)]
#[Small]
final class ProcesslistTest extends TestCase
{
    public function testRowsListsTheSessionsConnected(): void
    {
        $instance = new Instance();
        $idle = $instance->connect(database: null);
        $s = $instance->connect();

        $result1 = $s->query('SELECT * FROM performance_schema.processlist')[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertContains($result1->rows[0][5], ['0', '1']);
        self::assertContains($result1->rows[1][5], ['0', '1']);
        self::assertContains($result1->rows[2][5], ['0', '1']);
        self::assertSame([[(string) $idle->id, 'root', 'localhost', null, 'Sleep', '', null], [(string) $s->id, 'root', 'localhost', null, 'Query', 'executing', 'SELECT * FROM performance_schema.processlist'], ['1', 'event_scheduler', 'localhost', null, 'Daemon', 'Waiting on empty queue', null]], array_map(static fn (array $row): array => array_values(array_diff_key(array_slice($row, 0, 8), [5 => true])), $result1->rows));
    }

    public function testRowsWarnsThatInformationSchemaProcesslistIsDeprecated(): void
    {
        $s = (new Instance())->connect();
        $s->query('SELECT COUNT(*) FROM information_schema.PROCESSLIST');

        $result2 = $s->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(ResultSet::class, $result2);
        self::assertSame([['Warning', '1287', "'INFORMATION_SCHEMA.PROCESSLIST' is deprecated and will be removed in a future release. Please use performance_schema.processlist instead"]], $result2->rows);
    }

    public function testRowDescribesASession(): void
    {
        $s = (new Instance())->connect();

        $row = Processlist::row($s, false, 'init');
        self::assertContains($row['TIME'], [0, 1]);
        self::assertSame(['ID' => $s->id, 'USER' => 'root', 'HOST' => 'localhost', 'DB' => null, 'COMMAND' => 'Sleep', 'STATE' => '', 'INFO' => null, 'EXECUTION_ENGINE' => 'PRIMARY'], array_diff_key($row, ['TIME' => true]));
    }

    public function testRowCountsIdleTimeAndResetsAtTheNextStatement(): void
    {
        $s = (new Instance())->connect();
        $s->query('DO 1');
        $s->instance->registry->threads->pass(3);
        self::assertContains(Processlist::row($s, false, 'init')['TIME'], [3, 4]);
        $s->query('DO 1');
        self::assertContains(Processlist::row($s, false, 'init')['TIME'], [0, 1]);
    }

    public function testRowUsesThePinnedClockEvenWhileIdle(): void
    {
        $s = (new Instance())->connect();
        $s->query('SET timestamp=1700000000');
        $before = (int) floor($s->instance->registry->threads->now());
        $row = Processlist::row($s, false, 'init');
        $after = (int) floor($s->instance->registry->threads->now());

        self::assertGreaterThanOrEqual($before - 1700000000, $row['TIME']);
        self::assertLessThanOrEqual($after - 1700000000, $row['TIME']);
    }
}
