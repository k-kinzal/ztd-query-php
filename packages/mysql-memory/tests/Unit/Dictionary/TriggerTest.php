<?php

declare(strict_types=1);

namespace Tests\Unit\Dictionary;

use MySqlMemory\Dictionary\Trigger;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Trigger::class)]
#[Small]
final class TriggerTest extends TestCase
{
    public function testCreateWritesTheTriggerWithoutItsOrder(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('CREATE TRIGGER z1 BEFORE INSERT ON t FOR EACH ROW SET @a = 1');
        $session->query('CREATE TRIGGER c4 BEFORE INSERT ON t FOR EACH ROW FOLLOWS z1 SET NEW.a = NEW.a + 1');

        $schema = $session->instance->dictionary->schema('d');
        self::assertNotNull($schema);
        self::assertSame('CREATE DEFINER=`root`@`%` TRIGGER `c4` BEFORE INSERT ON `t` FOR EACH ROW SET NEW.a = NEW.a + 1', $schema->triggers[1]->create());
    }
}
