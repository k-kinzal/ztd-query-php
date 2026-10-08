<?php

declare(strict_types=1);

namespace Tests\Unit\System\Program;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\System\Program\Events;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Events::class)]
#[Small]
final class EventsTest extends TestCase
{
    public function testRowsDescribesEachEvent(): void
    {
        $s = (new Instance())->connect();
        $s->query('CREATE DATABASE d');
        $s->query('USE d');
        $s->query("CREATE EVENT ev ON SCHEDULE EVERY 1 DAY STARTS '2030-01-01 00:00:00' DISABLE COMMENT 'evc' DO SELECT 1");
        $s->query("CREATE EVENT ev2 ON SCHEDULE AT '2031-01-01 00:00:00' DO SELECT 2");

        $result1 = $s->query("SELECT EVENT_NAME, DEFINER, TIME_ZONE, EVENT_DEFINITION, EVENT_TYPE, EXECUTE_AT, INTERVAL_VALUE, INTERVAL_FIELD, STARTS, STATUS, ON_COMPLETION, EVENT_COMMENT, ORIGINATOR FROM information_schema.EVENTS WHERE EVENT_SCHEMA = 'd'")[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertSame([['ev', 'root@%', 'SYSTEM', 'SELECT 1', 'RECURRING', null, '1', 'DAY', '2030-01-01 00:00:00', 'DISABLED', 'NOT PRESERVE', 'evc', '1'], ['ev2', 'root@%', 'SYSTEM', 'SELECT 2', 'ONE TIME', '2031-01-01 00:00:00', null, null, null, 'ENABLED', 'NOT PRESERVE', '', '1']], $result1->rows);
    }
}
