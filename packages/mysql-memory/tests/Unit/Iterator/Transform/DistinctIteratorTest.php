<?php

declare(strict_types=1);

namespace Tests\Unit\Iterator\Transform;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Instance;
use MySqlMemory\Iterator\Source\WorkingTableIterator;
use MySqlMemory\Iterator\Transform\DistinctIterator;
use MySqlMemory\Plan\Path\Source\WorkingTable;
use MySqlMemory\Plan\Path\Transform\Distinct;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(DistinctIterator::class)]
#[Small]
final class DistinctIteratorTest extends TestCase
{
    public function testReadPassesTheFirstRowOfEachSetOfEqualLeadingValues(): void
    {
        $session = (new Instance())->connect();
        $working = new WorkingTable(2);
        $working->rows = [['a', 1], ['A', 2], [null, 3], [null, 4], ['b', 5]];
        $iterator = new DistinctIterator(new Distinct($working, [Domain::string(1, Collation::known('utf8mb4_0900_ai_ci'))]), new WorkingTableIterator($working));
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertSame(['a', 1], $iterator->read());
        self::assertSame([null, 3], $iterator->read());
        self::assertSame(['b', 5], $iterator->read());
        self::assertNull($iterator->read());
    }

    public function testReadComparesStringsByTheirCollation(): void
    {
        $session = (new Instance())->connect();
        $working = new WorkingTable(1);
        $working->rows = [['a'], ['A']];
        $iterator = new DistinctIterator(new Distinct($working, [Domain::string(1, Collation::known('utf8mb4_bin'))]), new WorkingTableIterator($working));
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertSame([['a'], ['A'], null], [$iterator->read(), $iterator->read(), $iterator->read()]);
    }

    public function testInitForgetsTheRowsSeen(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $working = new WorkingTable(1);
        $working->rows = [[1], [1]];
        $iterator = new DistinctIterator(new Distinct($working, [Domain::integer()]), new WorkingTableIterator($working));
        $iterator->init($frame);
        $first = [$iterator->read(), $iterator->read()];
        $iterator->init($frame);

        self::assertSame([[1], null], $first);
        self::assertSame([1], $iterator->read());
    }
}
