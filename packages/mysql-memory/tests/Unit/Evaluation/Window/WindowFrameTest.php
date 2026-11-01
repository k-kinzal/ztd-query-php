<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Window;

use MySqlMemory\Evaluation\Window\WindowFrame;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameUnit;

#[CoversClass(WindowFrame::class)]
#[Small]
final class WindowFrameTest extends TestCase
{
    public function testDefaultEndsAtTheLastPeerOfAnOrderedWindow(): void
    {
        $frame = WindowFrame::default(true);

        self::assertSame([FrameUnit::Range, FrameBoundKind::UnboundedPreceding, FrameBoundKind::CurrentRow], [$frame->unit, $frame->start->kind, $frame->end->kind]);
    }

    public function testDefaultReadsTheWholePartitionOfAWindowWithoutOrder(): void
    {
        self::assertSame(FrameBoundKind::UnboundedFollowing, WindowFrame::default(false)->end->kind);
    }
}
