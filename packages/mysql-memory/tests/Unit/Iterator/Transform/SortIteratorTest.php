<?php

declare(strict_types=1);

namespace Tests\Unit\Iterator\Transform;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Instance;
use MySqlMemory\Iterator\Source\WorkingTableIterator;
use MySqlMemory\Iterator\Transform\SortIterator;
use MySqlMemory\Plan\Path\Source\WorkingTable;
use MySqlMemory\Plan\Path\Transform\Sort;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(SortIterator::class)]
#[Small]
final class SortIteratorTest extends TestCase
{
    public function testInitSortsAscendingWithNullFirstAndKeepsTiesInInputOrder(): void
    {
        $session = (new Instance())->connect();
        $working = new WorkingTable(2);
        $working->rows = [['b', 1], ['a', 2], ['A', 3], [null, 4]];
        $iterator = new SortIterator(new Sort($working, [[0, Domain::string(1, Collation::known('utf8mb4_0900_ai_ci')), false]]), new WorkingTableIterator($working));
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertSame([null, 4], $iterator->read());
        self::assertSame(['a', 2], $iterator->read());
        self::assertSame(['A', 3], $iterator->read());
        self::assertSame(['b', 1], $iterator->read());
        self::assertNull($iterator->read());
    }

    public function testInitSortsDescendingWithNullLast(): void
    {
        $session = (new Instance())->connect();
        $working = new WorkingTable(1);
        $working->rows = [[2], [null], [10], [1]];
        $iterator = new SortIterator(new Sort($working, [[0, Domain::integer(), true]]), new WorkingTableIterator($working));
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertSame([[10], [2], [1], [null]], [$iterator->read(), $iterator->read(), $iterator->read(), $iterator->read()]);
    }

    public function testInitBreaksTiesOfTheFirstKeyByTheNext(): void
    {
        $session = (new Instance())->connect();
        $working = new WorkingTable(2);
        $working->rows = [[1, 1], [2, 5], [1, 3]];
        $iterator = new SortIterator(new Sort($working, [[0, Domain::integer(), false], [1, Domain::integer(), true]]), new WorkingTableIterator($working));
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertSame([[1, 3], [1, 1], [2, 5]], [$iterator->read(), $iterator->read(), $iterator->read()]);
    }

    public function testReadAnswersNullForAnEmptyInput(): void
    {
        $session = (new Instance())->connect();
        $working = new WorkingTable(1);
        $iterator = new SortIterator(new Sort($working, [[0, Domain::integer(), false]]), new WorkingTableIterator($working));
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertNull($iterator->read());
    }
}
