<?php

declare(strict_types=1);

namespace Tests\Unit\Iterator\Combine;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Instance;
use MySqlMemory\Iterator\Combine\SetOperationIterator;
use MySqlMemory\Iterator\Source\WorkingTableIterator;
use MySqlMemory\Plan\Path\Combine\SetOperation;
use MySqlMemory\Plan\Path\SetKind;
use MySqlMemory\Plan\Path\Source\WorkingTable;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(SetOperationIterator::class)]
#[Small]
final class SetOperationIteratorTest extends TestCase
{
    public function testInitKeepsEachDistinctRowOfBothSidesOnceForUnion(): void
    {
        $session = (new Instance())->connect();
        $left = new WorkingTable(1);
        $left->rows = [[1], [1], [2]];
        $right = new WorkingTable(1);
        $right->rows = [[2], [3]];
        $iterator = new SetOperationIterator(new SetOperation(SetKind::Union, true, $left, $right, [Domain::integer()]), new WorkingTableIterator($left), new WorkingTableIterator($right));
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertSame([[1], [2], [3], null], [$iterator->read(), $iterator->read(), $iterator->read(), $iterator->read()]);
    }

    public function testInitKeepsEveryRowOfBothSidesForUnionAll(): void
    {
        $session = (new Instance())->connect();
        $left = new WorkingTable(1);
        $left->rows = [[1], [1], [2]];
        $right = new WorkingTable(1);
        $right->rows = [[2]];
        $iterator = new SetOperationIterator(new SetOperation(SetKind::Union, false, $left, $right, [Domain::integer()]), new WorkingTableIterator($left), new WorkingTableIterator($right));
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertSame([[1], [1], [2], [2], null], [$iterator->read(), $iterator->read(), $iterator->read(), $iterator->read(), $iterator->read()]);
    }

    public function testInitKeepsTheDistinctRowsBothSidesHoldForIntersect(): void
    {
        $session = (new Instance())->connect();
        $left = new WorkingTable(1);
        $left->rows = [[1], [1], [2], [3]];
        $right = new WorkingTable(1);
        $right->rows = [[3], [1], [1]];
        $iterator = new SetOperationIterator(new SetOperation(SetKind::Intersect, true, $left, $right, [Domain::integer()]), new WorkingTableIterator($left), new WorkingTableIterator($right));
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertSame([[1], [3], null], [$iterator->read(), $iterator->read(), $iterator->read()]);
    }

    public function testInitKeepsARowAsOftenAsBothSidesHoldItForIntersectAll(): void
    {
        $session = (new Instance())->connect();
        $left = new WorkingTable(1);
        $left->rows = [[1], [1], [1], [2]];
        $right = new WorkingTable(1);
        $right->rows = [[1], [1], [3]];
        $iterator = new SetOperationIterator(new SetOperation(SetKind::Intersect, false, $left, $right, [Domain::integer()]), new WorkingTableIterator($left), new WorkingTableIterator($right));
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertSame([[1], [1], null], [$iterator->read(), $iterator->read(), $iterator->read()]);
    }

    public function testInitKeepsTheDistinctLeftRowsTheRightSideLacksForExcept(): void
    {
        $session = (new Instance())->connect();
        $left = new WorkingTable(1);
        $left->rows = [[1], [1], [2], [3]];
        $right = new WorkingTable(1);
        $right->rows = [[2]];
        $iterator = new SetOperationIterator(new SetOperation(SetKind::Except, true, $left, $right, [Domain::integer()]), new WorkingTableIterator($left), new WorkingTableIterator($right));
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertSame([[1], [3], null], [$iterator->read(), $iterator->read(), $iterator->read()]);
    }

    public function testInitKeepsARowAsOftenAsTheLeftSideHoldsItMoreForExceptAll(): void
    {
        $session = (new Instance())->connect();
        $left = new WorkingTable(1);
        $left->rows = [[1], [1], [1], [2]];
        $right = new WorkingTable(1);
        $right->rows = [[1]];
        $iterator = new SetOperationIterator(new SetOperation(SetKind::Except, false, $left, $right, [Domain::integer()]), new WorkingTableIterator($left), new WorkingTableIterator($right));
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertSame([[1], [1], [2], null], [$iterator->read(), $iterator->read(), $iterator->read(), $iterator->read()]);
    }

    public function testInitTreatsNullsAsEqual(): void
    {
        $session = (new Instance())->connect();
        $left = new WorkingTable(1);
        $left->rows = [[null], [1]];
        $right = new WorkingTable(1);
        $right->rows = [[null]];
        $iterator = new SetOperationIterator(new SetOperation(SetKind::Intersect, true, $left, $right, [Domain::integer()]), new WorkingTableIterator($left), new WorkingTableIterator($right));
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertSame([[null], null], [$iterator->read(), $iterator->read()]);
    }

    public function testRowsCutsEachRowToTheCombinedColumnsAndKeysEqualValuesAlike(): void
    {
        $session = (new Instance())->connect();
        $left = new WorkingTable(2);
        $left->rows = [['a', 1], ['A', 2]];
        $iterator = new SetOperationIterator(new SetOperation(SetKind::Union, true, $left, $left, [Domain::string(1, Collation::known('utf8mb4_0900_ai_ci'))]), new WorkingTableIterator($left), new WorkingTableIterator($left));

        $rows = $iterator->rows(new WorkingTableIterator($left), new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertSame([['a'], ['A']], array_column($rows, 1));
        self::assertSame($rows[0][0], $rows[1][0]);
    }

    public function testReadAnswersNullForTwoEmptySides(): void
    {
        $session = (new Instance())->connect();
        $left = new WorkingTable(1);
        $right = new WorkingTable(1);
        $iterator = new SetOperationIterator(new SetOperation(SetKind::Union, false, $left, $right, [Domain::integer()]), new WorkingTableIterator($left), new WorkingTableIterator($right));
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertNull($iterator->read());
    }
}
