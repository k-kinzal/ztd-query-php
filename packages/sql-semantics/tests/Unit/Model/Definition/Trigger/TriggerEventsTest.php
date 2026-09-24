<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Trigger\TriggerEvent;
use SqlSemantics\Model\Definition\Trigger\TriggerEvents;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(TriggerEvents::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class TriggerEventsTest extends TestCase
{
    public function testHasReportsOnlyTheNamedEvents(): void
    {
        $events = new TriggerEvents([TriggerEvent::Delete, TriggerEvent::Update], ['a']);
        self::assertTrue($events->has(TriggerEvent::Update));
        self::assertFalse($events->has(TriggerEvent::Insert));
        self::assertSame(['a'], $events->columns);
    }

    public function testAnEventIsNamedOnce(): void
    {
        $this->expectException(InvalidStructure::class);
        new TriggerEvents([TriggerEvent::Update, TriggerEvent::Update]);
    }

    public function testOnlyAnUpdateNamesColumns(): void
    {
        $this->expectException(InvalidStructure::class);
        new TriggerEvents([TriggerEvent::Insert], ['a']);
    }

    public function testUpdatedColumnsAreDistinct(): void
    {
        $this->expectException(InvalidStructure::class);
        new TriggerEvents([TriggerEvent::Update], ['a', 'a']);
    }

    public function testAtLeastOneEventIsNamed(): void
    {
        $this->expectException(InvalidStructure::class);
        new TriggerEvents([]);
    }
}
