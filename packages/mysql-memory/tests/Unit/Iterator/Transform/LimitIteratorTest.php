<?php

declare(strict_types=1);

namespace Tests\Unit\Iterator\Transform;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Instance;
use MySqlMemory\Iterator\Source\WorkingTableIterator;
use MySqlMemory\Iterator\Transform\LimitIterator;
use MySqlMemory\Plan\Path\Source\WorkingTable;
use MySqlMemory\Plan\Path\Transform\Limit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(LimitIterator::class)]
#[Small]
final class LimitIteratorTest extends TestCase
{
    public function testReadSkipsTheOffsetAndPassesAtMostTheCount(): void
    {
        $session = (new Instance())->connect();
        $working = new WorkingTable(1);
        $working->rows = [[1], [2], [3], [4], [5]];
        $iterator = new LimitIterator(new Limit($working, 2, 1), new WorkingTableIterator($working));
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertSame([2], $iterator->read());
        self::assertSame([3], $iterator->read());
        self::assertNull($iterator->read());
    }

    public function testReadPassesEveryRowAfterTheOffsetWithoutACount(): void
    {
        $session = (new Instance())->connect();
        $working = new WorkingTable(1);
        $working->rows = [[1], [2], [3]];
        $iterator = new LimitIterator(new Limit($working, null, 2), new WorkingTableIterator($working));
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertSame([3], $iterator->read());
        self::assertNull($iterator->read());
    }

    public function testReadAnswersNoRowForACountOfZero(): void
    {
        $session = (new Instance())->connect();
        $working = new WorkingTable(1);
        $working->rows = [[1]];
        $iterator = new LimitIterator(new Limit($working, 0), new WorkingTableIterator($working));
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertNull($iterator->read());
    }

    public function testReadAnswersNullWhenTheInputEndsBeforeTheCount(): void
    {
        $session = (new Instance())->connect();
        $working = new WorkingTable(1);
        $working->rows = [[1], [2]];
        $iterator = new LimitIterator(new Limit($working, 10, 1), new WorkingTableIterator($working));
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertSame([2], $iterator->read());
        self::assertNull($iterator->read());
    }

    public function testInitCountsTheRowsAgain(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $working = new WorkingTable(1);
        $working->rows = [[1], [2]];
        $iterator = new LimitIterator(new Limit($working, 1), new WorkingTableIterator($working));
        $iterator->init($frame);
        $first = [$iterator->read(), $iterator->read()];
        $iterator->init($frame);

        self::assertSame([[1], null], $first);
        self::assertSame([1], $iterator->read());
    }
}
