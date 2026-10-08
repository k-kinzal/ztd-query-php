<?php

declare(strict_types=1);

namespace Tests\Unit\Iterator\Transform;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Evaluation\Operator\Numeric;
use MySqlMemory\Instance;
use MySqlMemory\Iterator\Source\WorkingTableIterator;
use MySqlMemory\Iterator\Transform\FilterIterator;
use MySqlMemory\Plan\Path\Source\WorkingTable;
use MySqlMemory\Plan\Path\Transform\Filter;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(FilterIterator::class)]
#[Small]
final class FilterIteratorTest extends TestCase
{
    public function testReadPassesOnlyTheRowsTheConditionIsTrueFor(): void
    {
        $session = (new Instance())->connect();
        $working = new WorkingTable(2);
        $working->rows = [[1, 'a'], [0, 'b'], [null, 'c'], [5, 'd']];
        $iterator = new FilterIterator(new Filter($working, new ColumnRead(Domain::integer(), 0)), new WorkingTableIterator($working));
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertSame([1, 'a'], $iterator->read());
        self::assertSame([5, 'd'], $iterator->read());
        self::assertNull($iterator->read());
    }

    public function testInitRestartsTheInput(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $working = new WorkingTable(1);
        $working->rows = [[0], [2]];
        $iterator = new FilterIterator(new Filter($working, new ColumnRead(Domain::integer(), 0)), new WorkingTableIterator($working));
        $iterator->init($frame);
        $first = [$iterator->read(), $iterator->read()];
        $iterator->init($frame);

        self::assertSame([[2], null], $first);
        self::assertSame([2], $iterator->read());
    }

    public function testHoldsEvaluatesThePreconditionOnceForTheStatement(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $working = new WorkingTable(1);
        $working->rows = [[1], [2]];
        $iterator = new FilterIterator(new Filter($working, new ColumnRead(Domain::integer(), 0), new Numeric(new Constant(Domain::string(2, Collation::known('utf8mb4_0900_ai_ci')), '1x'), false)), new WorkingTableIterator($working));
        $iterator->init($frame);
        $rows = [$iterator->read(), $iterator->read(), $iterator->read()];
        $iterator->init($frame);

        self::assertSame([[1], [2], null], $rows);
        self::assertSame([[1], true], [$iterator->read(), $iterator->holds()]);
        self::assertSame(1, $session->diagnostics->count());
    }

    public function testReadReadsNoRowWhenThePreconditionIsNotTrue(): void
    {
        $session = (new Instance())->connect();
        $working = new WorkingTable(1);
        $working->rows = [[1], [2]];
        $iterator = new FilterIterator(new Filter($working, new ColumnRead(Domain::integer(), 0), new Constant(Domain::null(), null)), new WorkingTableIterator($working));
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertSame([null, false], [$iterator->read(), $iterator->holds()]);
    }
}
