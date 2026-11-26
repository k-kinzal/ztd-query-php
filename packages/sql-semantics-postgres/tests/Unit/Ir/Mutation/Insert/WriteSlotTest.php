<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Mutation\Insert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\Insert\WriteSlot;

#[CoversClass(WriteSlot::class)]
#[Small]
final class WriteSlotTest extends TestCase
{
    public function testKeepsTheNativeWriteRole(): void
    {
        $slot = new WriteSlot(1, ColumnKind::Text);

        self::assertSame(1, $slot->slot);
        self::assertSame(ColumnKind::Text, $slot->kind);
        self::assertSame(0, $slot->occurrence);
    }

    public function testRejectsAnOutsideOccurrence(): void
    {
        $this->expectExceptionMessage('An INSERT write slot must belong to the target.');

        new WriteSlot(0, ColumnKind::Int4, 1);
    }
}
