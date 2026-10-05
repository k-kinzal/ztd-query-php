<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\EventStatus;

#[CoversClass(EventStatus::class)]
#[Small]
final class EventStatusTest extends TestCase
{
    public function testCasesKeepBothSpellingsOfTheReplicaStatus(): void
    {
        self::assertSame(['Enable', 'Disable', 'DisableOnSlave', 'DisableOnReplica'], array_map(static fn (EventStatus $status): string => $status->name, EventStatus::cases()));
    }
}
