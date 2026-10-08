<?php

declare(strict_types=1);

namespace Tests\Unit\Iterator\Transform;

use MySqlMemory\Evaluation\Aggregate\Accumulation;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Instance;
use MySqlMemory\Iterator\Source\WorkingTableIterator;
use MySqlMemory\Iterator\Transform\AggregateIterator;
use MySqlMemory\Plan\Path\Source\WorkingTable;
use MySqlMemory\Plan\Path\Transform\Aggregate;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(AggregateIterator::class)]
#[Small]
final class AggregateIteratorTest extends TestCase
{
    public function testInitAnswersTheFirstRowAndTheAggregatesOfEachGroupInGroupOrder(): void
    {
        $session = (new Instance())->connect();
        $working = new WorkingTable(2);
        $working->rows = [['b', 1], ['a', 2], ['B', 3], [null, 4]];
        $count = new Accumulation(AggregateFunction::Count, [], false, Domain::integer());
        $iterator = new AggregateIterator(new Aggregate($working, [new ColumnRead(Domain::string(1, Collation::known('utf8mb4_0900_ai_ci')), 0)], [$count]), new WorkingTableIterator($working));
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertSame([null, 4, 1], $iterator->read());
        self::assertSame(['a', 2, 1], $iterator->read());
        self::assertSame(['b', 1, 2], $iterator->read());
        self::assertNull($iterator->read());
    }

    public function testInitAnswersOneGroupForAnEmptyInputWithoutGroupingExpressions(): void
    {
        $session = (new Instance())->connect();
        $working = new WorkingTable(2);
        $count = new Accumulation(AggregateFunction::Count, [], false, Domain::integer());
        $iterator = new AggregateIterator(new Aggregate($working, [], [$count]), new WorkingTableIterator($working));
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertSame([null, null, 0], $iterator->read());
        self::assertNull($iterator->read());
    }

    public function testInitAnswersNoGroupForAnEmptyInputWithGroupingExpressions(): void
    {
        $session = (new Instance())->connect();
        $working = new WorkingTable(1);
        $count = new Accumulation(AggregateFunction::Count, [], false, Domain::integer());
        $iterator = new AggregateIterator(new Aggregate($working, [new ColumnRead(Domain::integer(), 0)], [$count]), new WorkingTableIterator($working));
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertNull($iterator->read());
    }

    public function testInitFoldsOnlyTheNonNullArgumentsOfAnAggregate(): void
    {
        $session = (new Instance())->connect();
        $working = new WorkingTable(1);
        $working->rows = [[1], [null], [3]];
        $count = new Accumulation(AggregateFunction::Count, [new ColumnRead(Domain::integer(), 0)], false, Domain::integer());
        $iterator = new AggregateIterator(new Aggregate($working, [], [$count]), new WorkingTableIterator($working));
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertSame([1, 2], $iterator->read());
    }

    public function testSortedOrdersGroupsByTheirGroupingValues(): void
    {
        $working = new WorkingTable(2);
        $iterator = new AggregateIterator(new Aggregate($working, [new ColumnRead(Domain::integer(), 0), new ColumnRead(Domain::integer(), 1)], []), new WorkingTableIterator($working));

        $sorted = $iterator->sorted([[[2, 1], [2, 1], []], [[1, 9], [1, 9], []], [[2, 0], [2, 0], []], [[null, 5], [null, 5], []]]);

        self::assertSame([[null, 5], [1, 9], [2, 0], [2, 1]], array_column($sorted, 0));
    }

    public function testEmitAppendsTheResultOfEachAccumulatorToTheFirstRow(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $working = new WorkingTable(2);
        $count = new Accumulation(AggregateFunction::Count, [], false, Domain::integer());
        $iterator = new AggregateIterator(new Aggregate($working, [], [$count]), new WorkingTableIterator($working));
        $accumulator = $count->start();
        $accumulator->add($frame);
        $accumulator->add($frame);

        self::assertSame([[7, 'x', 2], [8, 'y', 0]], $iterator->emit([[[7, 'x'], [], [$accumulator]], [[8, 'y'], [], [$count->start()]]], $frame));
    }

    public function testReadAnswersNullAfterTheLastGroup(): void
    {
        $session = (new Instance())->connect();
        $working = new WorkingTable(1);
        $working->rows = [[1], [1]];
        $iterator = new AggregateIterator(new Aggregate($working, [new ColumnRead(Domain::integer(), 0)], []), new WorkingTableIterator($working));
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertSame([[1], null], [$iterator->read(), $iterator->read()]);
    }

    public function testInitAddsTheSuperAggregateRowsWithRollup(): void
    {
        $session = (new Instance())->connect();
        $working = new WorkingTable(2);
        $working->rows = [[2, 'x'], [1, 'y'], [1, 'x'], [2, 'x']];
        $count = new Accumulation(AggregateFunction::Count, [], false, Domain::integer());
        $groups = [new ColumnRead(Domain::integer(), 0), new ColumnRead(Domain::string(1, Collation::known('utf8mb4_0900_ai_ci')), 1)];
        $iterator = new AggregateIterator(new Aggregate($working, $groups, [$count], true, [0, 1]), new WorkingTableIterator($working));
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertSame(
            [[1, 'x', 1, 1, 'x', 0], [1, 'y', 1, 1, 'y', 0], [1, null, 2, 1, null, 1], [2, 'x', 2, 2, 'x', 0], [2, null, 2, 2, null, 1], [null, null, 4, null, null, 2], null],
            [$iterator->read(), $iterator->read(), $iterator->read(), $iterator->read(), $iterator->read(), $iterator->read(), $iterator->read()],
        );
    }

    public function testRollupKeepsAColumnThatAKeptGroupingExpressionReads(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $working = new WorkingTable(1);
        $count = new Accumulation(AggregateFunction::Count, [], false, Domain::integer());
        $iterator = new AggregateIterator(new Aggregate($working, [new ColumnRead(Domain::integer(), 0), new ColumnRead(Domain::integer(), 0)], [$count], true, [0, 0]), new WorkingTableIterator($working));
        $started = $count->start();
        $started->add($frame);

        self::assertSame([[5, 1, 5, 5, 0], [5, 1, 5, null, 1], [null, 1, null, null, 2]], $iterator->rollup([[[5], [5, 5], [$started], [[5]]]], $frame));
    }

    public function testAgreeComparesTheLeadingGroupingValues(): void
    {
        $working = new WorkingTable(2);
        $iterator = new AggregateIterator(new Aggregate($working, [new ColumnRead(Domain::integer(), 0), new ColumnRead(Domain::integer(), 1)], [], true, [0, 1]), new WorkingTableIterator($working));

        self::assertSame([true, true, false], [$iterator->agree([1, 2], [1, 3], 0), $iterator->agree([1, 2], [1, 3], 1), $iterator->agree([1, 2], [1, 3], 2)]);
    }

    public function testRowAppendsTheAggregatesTheGroupingValuesAndTheRolledUpCount(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $working = new WorkingTable(1);
        $count = new Accumulation(AggregateFunction::Count, [], false, Domain::integer());
        $iterator = new AggregateIterator(new Aggregate($working, [new ColumnRead(Domain::integer(), 0)], [$count], true, [0]), new WorkingTableIterator($working));

        self::assertSame([null, 0, null, 1], $iterator->row([null], [$count->start()], [null], 1, $frame));
    }
}
