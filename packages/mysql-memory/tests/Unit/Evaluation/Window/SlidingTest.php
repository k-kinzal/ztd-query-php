<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Window;

use MySqlMemory\Evaluation\Aggregate\Accumulation;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Evaluation\Window\Bound;
use MySqlMemory\Evaluation\Window\Partition;
use MySqlMemory\Evaluation\Window\Sliding;
use MySqlMemory\Evaluation\Window\WindowFrame;
use MySqlMemory\Instance;
use MySqlMemory\Plan\Path\Source\WorkingTable;
use MySqlMemory\Plan\Path\Transform\Window;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameUnit;

#[CoversClass(Sliding::class)]
#[Small]
final class SlidingTest extends TestCase
{
    public function testSupportsExactSumsWithoutApplyingInverseArithmeticToFloatingResults(): void
    {
        $argument = new ColumnRead(Domain::integer(), 0);

        self::assertTrue(Sliding::supports(new Accumulation(AggregateFunction::Sum, [$argument], false, Domain::decimal(33, 0))));
        self::assertFalse(Sliding::supports(new Accumulation(AggregateFunction::Sum, [$argument], false, Domain::double())));
        self::assertFalse(Sliding::supports(new Accumulation(AggregateFunction::Minimum, [$argument], false, Domain::integer())));
    }

    public function testValueRemovesLeavingRowsAndKeepsNullFramesEmpty(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $bounds = new WindowFrame(FrameUnit::Rows, new Bound(FrameBoundKind::CurrentRow), new Bound(FrameBoundKind::CurrentRow));
        $partition = new Partition([[2], [null], [5]], [[], [], []], new Window(new WorkingTable(1), [], [], $bounds, []));
        $sliding = new Sliding(new Accumulation(AggregateFunction::Sum, [new ColumnRead(Domain::integer(), 0)], false, Domain::decimal(33, 0)));

        self::assertSame(['2', null, '5'], [\MySqlMemory\Evaluation\Convert::toText($sliding->value($partition, 0, $frame), Domain::decimal(33, 0)), \MySqlMemory\Evaluation\Convert::toText($sliding->value($partition, 1, $frame), Domain::decimal(33, 0)), \MySqlMemory\Evaluation\Convert::toText($sliding->value($partition, 2, $frame), Domain::decimal(33, 0))]);
    }
}
