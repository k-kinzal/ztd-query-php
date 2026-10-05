<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerEvent;

#[CoversClass(TriggerEvent::class)]
#[Small]
final class TriggerEventTest extends TestCase
{
    public function testCasesHoldTheirKeywords(): void
    {
        self::assertSame(['INSERT', 'UPDATE', 'DELETE'], array_map(static fn (TriggerEvent $event): string => $event->value, TriggerEvent::cases()));
    }
}
