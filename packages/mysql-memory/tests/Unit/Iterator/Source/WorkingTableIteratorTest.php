<?php

declare(strict_types=1);

namespace Tests\Unit\Iterator\Source;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Instance;
use MySqlMemory\Iterator\Source\WorkingTableIterator;
use MySqlMemory\Plan\Path\Source\WorkingTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(WorkingTableIterator::class)]
#[Small]
final class WorkingTableIteratorTest extends TestCase
{
    public function testReadAnswersTheRowsOfTheLastIterationInOrder(): void
    {
        $session = (new Instance())->connect();
        $working = new WorkingTable(2);
        $working->rows = [[1, 'a'], [2, null]];
        $iterator = new WorkingTableIterator($working);
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertSame([1, 'a'], $iterator->read());
        self::assertSame([2, null], $iterator->read());
        self::assertNull($iterator->read());
    }

    public function testInitTakesTheRowsTheWorkingTableHoldsWhenItStarts(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $working = new WorkingTable(1);
        $working->rows = [[1]];
        $iterator = new WorkingTableIterator($working);
        $iterator->init($frame);
        $working->rows = [[2], [3]];
        $first = [$iterator->read(), $iterator->read()];
        $iterator->init($frame);

        self::assertSame([[1], null], $first);
        self::assertSame([2], $iterator->read());
        self::assertSame([3], $iterator->read());
    }
}
