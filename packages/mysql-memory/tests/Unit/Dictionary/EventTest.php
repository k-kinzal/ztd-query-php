<?php

declare(strict_types=1);

namespace Tests\Unit\Dictionary;

use MySqlMemory\Dictionary\Event;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Event::class)]
#[Small]
final class EventTest extends TestCase
{
    public function testCreateWritesAOneTimeEvent(): void
    {
        $event = new Event('d', 'e2', ['root', '%'], 'SYSTEM', '2030-01-01 00:00:00', null, null, null, 'DISABLED', true, 'c', 'SELECT 2', '', '2026-01-01 00:00:00', '2026-01-01 00:00:00', ['utf8mb4', 'utf8mb4_0900_ai_ci', 'utf8mb4_0900_ai_ci']);

        self::assertSame("CREATE DEFINER=`root`@`%` EVENT `e2` ON SCHEDULE AT '2030-01-01 00:00:00' ON COMPLETION PRESERVE DISABLE COMMENT 'c' DO SELECT 2", $event->create());
    }

    public function testCreateWritesARecurringEventWithItsStartAndEnd(): void
    {
        $event = new Event('d', 'e3', ['root', '%'], 'SYSTEM', null, ["'1:30'", 'HOUR_MINUTE'], '2030-01-01 00:00:00', '2031-01-01 00:00:00', 'SLAVESIDE_DISABLED', false, '', 'SELECT 1', '', '2026-01-01 00:00:00', '2026-01-01 00:00:00', ['utf8mb4', 'utf8mb4_0900_ai_ci', 'utf8mb4_0900_ai_ci']);

        self::assertSame("CREATE DEFINER=`root`@`%` EVENT `e3` ON SCHEDULE EVERY '1:30' HOUR_MINUTE STARTS '2030-01-01 00:00:00' ENDS '2031-01-01 00:00:00' ON COMPLETION NOT PRESERVE DISABLE ON REPLICA DO SELECT 1", $event->create());
    }
}
