<?php

declare(strict_types=1);

namespace Tests\Unit\System\Program;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\System\Program\Triggers;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Triggers::class)]
#[Small]
final class TriggersTest extends TestCase
{
    public function testRowsListsTheTriggersInTheOrderTheyFire(): void
    {
        $s = (new Instance())->connect();
        $s->query('CREATE DATABASE d');
        $s->query('USE d');
        $s->query('CREATE TABLE t (a INT)');
        $s->query('CREATE TRIGGER tb BEFORE INSERT ON t FOR EACH ROW SET NEW.a = NEW.a + 1');
        $s->query('CREATE TRIGGER ta BEFORE INSERT ON t FOR EACH ROW FOLLOWS tb SET NEW.a = NEW.a + 2');
        $s->query('CREATE TRIGGER tu AFTER UPDATE ON t FOR EACH ROW SET @x = 1');

        $result1 = $s->query("SELECT TRIGGER_NAME, EVENT_MANIPULATION, EVENT_OBJECT_TABLE, ACTION_ORDER, ACTION_STATEMENT, ACTION_ORIENTATION, ACTION_TIMING, ACTION_REFERENCE_OLD_ROW, ACTION_REFERENCE_NEW_ROW FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = 'd'")[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertSame([['tb', 'INSERT', 't', '1', 'SET NEW.a = NEW.a + 1', 'ROW', 'BEFORE', 'OLD', 'NEW'], ['ta', 'INSERT', 't', '2', 'SET NEW.a = NEW.a + 2', 'ROW', 'BEFORE', 'OLD', 'NEW'], ['tu', 'UPDATE', 't', '1', 'SET @x = 1', 'ROW', 'AFTER', 'OLD', 'NEW']], $result1->rows);
    }
}
