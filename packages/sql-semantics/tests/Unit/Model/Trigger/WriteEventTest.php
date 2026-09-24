<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Trigger\WriteEvent;

#[CoversClass(WriteEvent::class)]
final class WriteEventTest extends TestCase
{
    public function testRepresentsEveryWriteOperation(): void
    {
        self::assertSame(['INSERT', 'UPDATE', 'DELETE'], array_column(WriteEvent::cases(), 'value'));
    }

    #[TestWith([WriteEvent::Insert])]
    #[TestWith([WriteEvent::Update])]
    #[TestWith([WriteEvent::Delete])]
    public function testOperationIsTheEventItself(WriteEvent $event): void
    {
        self::assertSame($event, $event->operation());
    }
}
