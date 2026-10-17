<?php

declare(strict_types=1);

namespace Tests\Unit\Iterator\Combine;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Evaluation\Operator\Comparison\Comparator;
use MySqlMemory\Evaluation\Operator\Comparison\Compare;
use MySqlMemory\Instance;
use MySqlMemory\Iterator\Combine\NestedLoopJoinIterator;
use MySqlMemory\Iterator\Source\InlineIterator;
use MySqlMemory\Iterator\Source\WorkingTableIterator;
use MySqlMemory\Plan\Path\Combine\NestedLoopJoin;
use MySqlMemory\Plan\Path\JoinKind;
use MySqlMemory\Plan\Path\Source\Inline;
use MySqlMemory\Plan\Path\Source\WorkingTable;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

#[CoversClass(NestedLoopJoinIterator::class)]
#[Small]
final class NestedLoopJoinIteratorTest extends TestCase
{
    public function testReadPairsEveryRowOfBothInputsWithoutACondition(): void
    {
        $session = (new Instance())->connect();
        $left = new WorkingTable(1);
        $left->rows = [[1], [2]];
        $right = new WorkingTable(1);
        $right->rows = [['a'], ['b']];
        $iterator = new NestedLoopJoinIterator(new NestedLoopJoin($left, $right, JoinKind::Inner, null), new WorkingTableIterator($left), new WorkingTableIterator($right));
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertSame([[1, 'a'], [1, 'b'], [2, 'a'], [2, 'b'], null], [$iterator->read(), $iterator->read(), $iterator->read(), $iterator->read(), $iterator->read()]);
    }

    public function testReadKeepsThePairsThatMeetTheCondition(): void
    {
        $session = (new Instance())->connect();
        $left = new WorkingTable(1);
        $left->rows = [[1], [2], [3]];
        $right = new WorkingTable(1);
        $right->rows = [[3], [1], [1]];
        $condition = new Compare(ComparisonOperator::Equal, new ColumnRead(Domain::integer(), 0), new ColumnRead(Domain::integer(), 1), Comparator::of(Domain::integer(), Domain::integer(), '=', Collation::known('utf8mb4_0900_ai_ci')), Domain::integer(Field::LongLong, 1));
        $iterator = new NestedLoopJoinIterator(new NestedLoopJoin($left, $right, JoinKind::Inner, $condition), new WorkingTableIterator($left), new WorkingTableIterator($right));
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertSame([[1, 1], [1, 1], [3, 3], null], [$iterator->read(), $iterator->read(), $iterator->read(), $iterator->read()]);
    }

    public function testReadKeepsEachUnmatchedLeftRowWithNullsForALeftJoin(): void
    {
        $session = (new Instance())->connect();
        $left = new WorkingTable(1);
        $left->rows = [[1], [2]];
        $right = new WorkingTable(2);
        $right->rows = [[2, 'b']];
        $condition = new Compare(ComparisonOperator::Equal, new ColumnRead(Domain::integer(), 0), new ColumnRead(Domain::integer(), 1), Comparator::of(Domain::integer(), Domain::integer(), '=', Collation::known('utf8mb4_0900_ai_ci')), Domain::integer(Field::LongLong, 1));
        $iterator = new NestedLoopJoinIterator(new NestedLoopJoin($left, $right, JoinKind::Left, $condition), new WorkingTableIterator($left), new WorkingTableIterator($right));
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertSame([[1, null, null], [2, 2, 'b'], null], [$iterator->read(), $iterator->read(), $iterator->read()]);
    }

    public function testReadKeepsEachUnmatchedRightRowWithNullsForARightJoin(): void
    {
        $session = (new Instance())->connect();
        $left = new WorkingTable(2);
        $left->rows = [[2, 'b']];
        $right = new WorkingTable(1);
        $right->rows = [[1], [2]];
        $condition = new Compare(ComparisonOperator::Equal, new ColumnRead(Domain::integer(), 0), new ColumnRead(Domain::integer(), 2), Comparator::of(Domain::integer(), Domain::integer(), '=', Collation::known('utf8mb4_0900_ai_ci')), Domain::integer(Field::LongLong, 1));
        $iterator = new NestedLoopJoinIterator(new NestedLoopJoin($left, $right, JoinKind::Right, $condition), new WorkingTableIterator($left), new WorkingTableIterator($right));
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertSame([[null, null, 1], [2, 'b', 2], null], [$iterator->read(), $iterator->read(), $iterator->read()]);
    }

    public function testReadLetsTheInnerInputReadTheOuterRow(): void
    {
        $session = (new Instance())->connect();
        $left = new WorkingTable(1);
        $left->rows = [[4], [5]];
        $inline = new Inline([[new ColumnRead(Domain::integer(), 0)]], 1);
        $iterator = new NestedLoopJoinIterator(new NestedLoopJoin($left, $inline, JoinKind::Inner, null, true), new WorkingTableIterator($left), new InlineIterator($inline));
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertSame([[4, 4], [5, 5], null], [$iterator->read(), $iterator->read(), $iterator->read()]);
    }

    public function testInitRestartsTheOuterInput(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $left = new WorkingTable(1);
        $left->rows = [[1]];
        $right = new WorkingTable(1);
        $right->rows = [[2]];
        $iterator = new NestedLoopJoinIterator(new NestedLoopJoin($left, $right, JoinKind::Inner, null), new WorkingTableIterator($left), new WorkingTableIterator($right));
        $iterator->init($frame);
        $iterator->read();
        $iterator->init($frame);

        self::assertSame([1, 2], $iterator->read());
    }

    public function testOuterIsTheRightInputOnlyForARightJoin(): void
    {
        $left = new WorkingTableIterator(new WorkingTable(1));
        $right = new WorkingTableIterator(new WorkingTable(1));
        $leftJoin = new NestedLoopJoinIterator(new NestedLoopJoin($left->path, $right->path, JoinKind::Left, null), $left, $right);
        $rightJoin = new NestedLoopJoinIterator(new NestedLoopJoin($left->path, $right->path, JoinKind::Right, null), $left, $right);

        self::assertSame($left, $leftJoin->outer());
        self::assertSame($right, $rightJoin->outer());
    }

    public function testInnerIsTheLeftInputOnlyForARightJoin(): void
    {
        $left = new WorkingTableIterator(new WorkingTable(1));
        $right = new WorkingTableIterator(new WorkingTable(1));
        $innerJoin = new NestedLoopJoinIterator(new NestedLoopJoin($left->path, $right->path, JoinKind::Inner, null), $left, $right);
        $rightJoin = new NestedLoopJoinIterator(new NestedLoopJoin($left->path, $right->path, JoinKind::Right, null), $left, $right);

        self::assertSame($right, $innerJoin->inner());
        self::assertSame($left, $rightJoin->inner());
    }
}
