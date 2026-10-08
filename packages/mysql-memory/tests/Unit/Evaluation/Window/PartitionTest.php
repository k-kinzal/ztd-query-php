<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Window;

use MySqlMemory\Evaluation\Aggregate\Accumulation;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Evaluation\Window\Analytic;
use MySqlMemory\Evaluation\Window\Bound;
use MySqlMemory\Evaluation\Window\Partition;
use MySqlMemory\Evaluation\Window\Shift;
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
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

#[CoversClass(Partition::class)]
#[Small]
final class PartitionTest extends TestCase
{
    public function testValuesRanksTheGroupsOfPeers(): void
    {
        $order = [[new ColumnRead(Domain::integer(), 1), false]];
        $partition = new Partition([[5, null], [8, null], [3, 1], [7, 1], [4, 2]], [[null], [null], [1], [1], [2]], new Window(new WorkingTable(2), [], $order, WindowFrame::default(true), [new Analytic(WindowFunctionKind::Rank, null, [], 1, Domain::integer()), new Analytic(WindowFunctionKind::DenseRank, null, [], 1, Domain::integer())]));
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));

        self::assertSame([[1, 1], [1, 1], [3, 2], [3, 2], [5, 3]], [$partition->values(0, $frame), $partition->values(1, $frame), $partition->values(2, $frame), $partition->values(3, $frame), $partition->values(4, $frame)]);
    }

    public function testPeersComparesTheOrderingValues(): void
    {
        $partition = new Partition([[1], [2], [3]], [['a'], ['A'], ['b']], new Window(new WorkingTable(1), [], [[new ColumnRead(Domain::string(1, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::known('utf8mb4_0900_ai_ci')), 0), false]], WindowFrame::default(true), []));

        self::assertSame([true, false], [$partition->peers(0, 1), $partition->peers(1, 2)]);
    }

    public function testValuesAnswersEachFunctionForARow(): void
    {
        $order = [[new ColumnRead(Domain::integer(), 1), false]];
        $functions = [new Analytic(WindowFunctionKind::RowNumber, null, [], 1, Domain::integer()), new Analytic(WindowFunctionKind::PercentRank, null, [], 1, Domain::double()), new Analytic(WindowFunctionKind::CumulativeDistribution, null, [], 1, Domain::double())];
        $partition = new Partition([[5, null], [8, null], [3, 1], [7, 1], [4, 2], [1, 3], [2, 3], [6, 3]], [[null], [null], [1], [1], [2], [3], [3], [3]], new Window(new WorkingTable(2), [], $order, WindowFrame::default(true), $functions));
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));

        self::assertSame([[3, 2 / 7, 0.5], [8, 5 / 7, 1.0]], [$partition->values(2, $frame), $partition->values(7, $frame)]);
    }

    public function testValueIsZeroForThePercentRankOfASingleRow(): void
    {
        $analytic = new Analytic(WindowFunctionKind::PercentRank, null, [], 1, Domain::double());
        $partition = new Partition([[1]], [[]], new Window(new WorkingTable(1), [], [], WindowFrame::default(false), [$analytic]));
        $session = (new Instance())->connect();

        self::assertSame(0.0, $partition->value($analytic, 0, new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0))));
    }

    public function testTileSplitsTheRowsLargerBucketsFirst(): void
    {
        $partition = new Partition(array_fill(0, 8, [1]), array_fill(0, 8, []), new Window(new WorkingTable(1), [], [], WindowFrame::default(false), []));

        self::assertSame([1, 1, 1, 2, 2, 2, 3, 3], array_map(static fn (int $row): int => $partition->tile($row, 3), range(0, 7)));
        self::assertSame(5, $partition->tile(4, 10));
    }

    public function testShiftedReadsAnotherRowOrTheDefaultOfTheCurrentOne(): void
    {
        $lag = new Analytic(WindowFunctionKind::Lag, null, [new ColumnRead(Domain::integer(), 0), new ColumnRead(Domain::integer(), 1)], 1, Domain::integer());
        $lead = new Analytic(WindowFunctionKind::Lead, null, [new ColumnRead(Domain::integer(), 0)], 2, Domain::integer());
        $partition = new Partition([[1, -1], [2, -2], [3, -3]], [[], [], []], new Window(new WorkingTable(2), [], [], WindowFrame::default(false), [$lag, $lead]));
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));

        self::assertSame([-1, 1, 3, null], [$partition->shifted($lag, 0, $frame), $partition->shifted($lag, 1, $frame), $partition->shifted($lead, 0, $frame), $partition->shifted($lead, 1, $frame)]);
    }

    public function testFramedReadsTheFirstLastOrNthRowOfTheFrame(): void
    {
        $frameClause = new WindowFrame(FrameUnit::Rows, new Bound(FrameBoundKind::Preceding, 1), new Bound(FrameBoundKind::Following, 1));
        $first = new Analytic(WindowFunctionKind::FirstValue, null, [new ColumnRead(Domain::integer(), 0)], 1, Domain::integer());
        $last = new Analytic(WindowFunctionKind::LastValue, null, [new ColumnRead(Domain::integer(), 0)], 1, Domain::integer());
        $third = new Analytic(WindowFunctionKind::NthValue, null, [new ColumnRead(Domain::integer(), 0)], 3, Domain::integer());
        $partition = new Partition([[5], [8], [3]], [[], [], []], new Window(new WorkingTable(1), [], [], $frameClause, [$first, $last, $third]));
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));

        self::assertSame([5, 8, null, 3], [$partition->framed($first, 0, $frame), $partition->framed($last, 0, $frame), $partition->framed($third, 0, $frame), $partition->framed($third, 1, $frame)]);
    }

    public function testAggregateFoldsTheRowsOfTheFrame(): void
    {
        $sum = new Analytic(null, new Accumulation(AggregateFunction::Sum, [new ColumnRead(Domain::integer(), 0)], false, Domain::decimal(33, 0)), [], 1, Domain::decimal(33, 0));
        $order = [[new ColumnRead(Domain::integer(), 1), false]];
        $partition = new Partition([[5, null], [8, null], [3, 1], [7, 1], [4, 2]], [[null], [null], [1], [1], [2]], new Window(new WorkingTable(2), [], $order, WindowFrame::default(true), [$sum]));
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));

        self::assertSame(['13', '23', '27'], [$partition->aggregate($sum, 0, $frame), $partition->aggregate($sum, 3, $frame), $partition->aggregate($sum, 4, $frame)]);
    }

    public function testExtentLeavesAFramePastThePartitionEmpty(): void
    {
        $frameClause = new WindowFrame(FrameUnit::Rows, new Bound(FrameBoundKind::Following, 2), new Bound(FrameBoundKind::Following, 3));
        $partition = new Partition([[1], [2], [3]], [[], [], []], new Window(new WorkingTable(1), [], [], $frameClause, []));
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));

        self::assertSame([[2, 2], [3, 2]], [$partition->extent(0, $frame), $partition->extent(1, $frame)]);
    }

    public function testEdgeFindsTheRowsWithinARangeAndTheNullPeers(): void
    {
        $key = new ColumnRead(Domain::integer(), 1);
        $order = [[$key, false]];
        $start = new Bound(FrameBoundKind::Preceding, 0, new Shift($key, '1', true, Domain::decimal(65, 30)));
        $partition = new Partition([[5, null], [8, null], [3, 1], [7, 1], [4, 2], [1, 3]], [[null], [null], [1], [1], [2], [3]], new Window(new WorkingTable(2), [], $order, new WindowFrame(FrameUnit::Range, $start, new Bound(FrameBoundKind::CurrentRow)), []));
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $frame->row = [4, 2];
        $at = $partition->edge($start, 4, $frame, true);
        $frame->row = [5, null];

        self::assertSame([2, 0], [$at, $partition->edge($start, 0, $frame, true)]);
    }

    public function testCompareReadsADateAsTheStartOfItsDay(): void
    {
        $partition = new Partition([], [], new Window(new WorkingTable(1), [], [], WindowFrame::default(false), []));

        self::assertSame([0, -1], [$partition->compare('2020-01-02', '2020-01-02 00:00:00', new Domain(Kind::DateTime, Field::DateTime, 26, 6)), $partition->compare(null, '2020-01-02', new Domain(Kind::Date, Field::Date, 10))]);
    }

    public function testMomentWritesTheTimeAndSixFractionalDigits(): void
    {
        self::assertSame(['2020-01-02 00:00:00.000000', '2020-01-02 10:00:00.500000'], [Partition::moment('2020-01-02'), Partition::moment('2020-01-02 10:00:00.5')]);
    }

    public function testConvertedTurnsADateIntoTheDatetimeOfTheResult(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));

        self::assertSame(['2020-01-01 00:00:00', '1.00'], [Partition::converted('2020-01-01', new Domain(Kind::Date, Field::Date, 10), new Domain(Kind::DateTime, Field::DateTime, 19), $frame), Partition::converted(1, Domain::integer(), Domain::decimal(12, 2), $frame)]);
    }
}
