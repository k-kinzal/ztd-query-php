<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Path\Transform;

use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Evaluation\Window\Analytic;
use MySqlMemory\Evaluation\Window\Bound;
use MySqlMemory\Evaluation\Window\WindowFrame;
use MySqlMemory\Plan\Path\Source\ZeroRows;
use MySqlMemory\Plan\Path\Transform\Window;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameUnit;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind;

#[CoversClass(Window::class)]
#[Small]
final class WindowTest extends TestCase
{
    public function testWidthAddsOneValuePerWindowFunction(): void
    {
        $rank = new Analytic(WindowFunctionKind::Rank, null, [], 1, Domain::integer());

        self::assertSame(4, (new Window(new ZeroRows(2), [], [], WindowFrame::default(false), [$rank, $rank]))->width());
    }
    public function testBufferedRequiresPartitionSizeForRangeFramesAndLead(): void
    {
        $rows = new WindowFrame(FrameUnit::Rows, new Bound(FrameBoundKind::CurrentRow), new Bound(FrameBoundKind::CurrentRow));
        $lead = new Analytic(WindowFunctionKind::Lead, null, [new ColumnRead(Domain::integer(), 0)], 1, Domain::integer());
        $input = new ZeroRows(1);

        self::assertFalse((new Window($input, [], [], $rows, []))->buffered());
        self::assertTrue((new Window($input, [], [], $rows, [$lead]))->buffered());
        self::assertTrue((new Window($input, [], [], WindowFrame::default(false), []))->buffered());
    }

    public function testFramedIgnoresBoundsForRankingCalls(): void
    {
        $rank = new Analytic(WindowFunctionKind::RowNumber, null, [], 1, Domain::integer());
        $window = new Window(new ZeroRows(1), [], [], WindowFrame::default(false), [$rank]);

        self::assertFalse($window->framed());
        self::assertFalse($window->buffered());
    }

}
