<?php

declare(strict_types=1);

namespace Tests\Unit\Iterator\Transform;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Evaluation\Window\Analytic;
use MySqlMemory\Evaluation\Window\WindowFrame;
use MySqlMemory\Instance;
use MySqlMemory\Iterator\Source\WorkingTableIterator;
use MySqlMemory\Iterator\Transform\WindowIterator;
use MySqlMemory\Plan\Path\Source\WorkingTable;
use MySqlMemory\Plan\Path\Transform\Window;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind;

#[CoversClass(WindowIterator::class)]
#[Small]
final class WindowIteratorTest extends TestCase
{
    public function testInitNumbersTheRowsOfEachPartitionInTheOrderOfTheWindow(): void
    {
        $session = (new Instance())->connect();
        $working = new WorkingTable(3);
        $working->rows = [[1, 2, 3], [2, 1, 3], [3, 2, 1], [4, null, 2], [5, 1, null]];
        $window = new Window($working, [new ColumnRead(Domain::integer(), 1)], [[new ColumnRead(Domain::integer(), 2), false]], WindowFrame::default(true), [new Analytic(WindowFunctionKind::RowNumber, null, [], 1, Domain::integer())]);
        $iterator = new WindowIterator($window, new WorkingTableIterator($working));
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertSame([[4, null, 2, 1], [5, 1, null, 1], [2, 1, 3, 2], [3, 2, 1, 1], [1, 2, 3, 2]], [$iterator->read(), $iterator->read(), $iterator->read(), $iterator->read(), $iterator->read()]);
        self::assertNull($iterator->read());
    }

    public function testSortedKeepsTheInputOrderOfAWindowWithoutKeys(): void
    {
        $working = new WorkingTable(1);
        $iterator = new WindowIterator(new Window($working, [], [], WindowFrame::default(false), []), new WorkingTableIterator($working));

        self::assertSame([0, 1, 2], $iterator->sorted([[], [], []], [[], [], []]));
    }

    public function testSortedOrdersDescendingKeysAndKeepsTiesInInputOrder(): void
    {
        $working = new WorkingTable(1);
        $iterator = new WindowIterator(new Window($working, [], [[new ColumnRead(Domain::integer(), 0), true]], WindowFrame::default(true), []), new WorkingTableIterator($working));

        self::assertSame([1, 3, 0, 2], $iterator->sorted([[], [], [], []], [[1], [3], [null], [3]]));
    }

    public function testSameComparesThePartitionValues(): void
    {
        $working = new WorkingTable(1);
        $iterator = new WindowIterator(new Window($working, [new ColumnRead(Domain::integer(), 0)], [], WindowFrame::default(false), []), new WorkingTableIterator($working));

        self::assertSame([true, false], [$iterator->same([null], [null]), $iterator->same([1], [2])]);
    }

    public function testEmitAddsTheRowsOfAPartitionWithoutTheEvaluatedArguments(): void
    {
        $session = (new Instance())->connect();
        $working = new WorkingTable(1);
        $iterator = new WindowIterator(new Window($working, [], [], WindowFrame::default(false), [new Analytic(WindowFunctionKind::FirstValue, null, [new ColumnRead(Domain::integer(), 1)], 1, Domain::integer())], [new ColumnRead(Domain::integer(), 0)]), new WorkingTableIterator($working));
        $iterator->emit([0, 1], [[7, 7], [8, 8]], [[], []], new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertSame([[7, 7], [8, 7]], [$iterator->read(), $iterator->read()]);
    }

    public function testReadAnswersNullOnceTheRowsAreRead(): void
    {
        $session = (new Instance())->connect();
        $working = new WorkingTable(1);
        $iterator = new WindowIterator(new Window($working, [], [], WindowFrame::default(false), []), new WorkingTableIterator($working));
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertNull($iterator->read());
    }
}
