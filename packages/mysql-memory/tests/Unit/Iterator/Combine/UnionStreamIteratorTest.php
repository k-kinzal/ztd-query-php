<?php

declare(strict_types=1);

namespace Tests\Unit\Iterator\Combine;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Instance;
use MySqlMemory\Iterator\Combine\UnionStreamIterator;
use MySqlMemory\Iterator\Source\WorkingTableIterator;
use MySqlMemory\Plan\Path\Combine\UnionStream;
use MySqlMemory\Plan\Path\Source\WorkingTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(UnionStreamIterator::class)]
#[Small]
final class UnionStreamIteratorTest extends TestCase
{
    public function testReadOpensTheSecondOperandOnlyAfterTheFirstIsExhausted(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $left = new WorkingTable(2);
        $left->rows = [[1, 2]];
        $right = new WorkingTable(2);
        $iterator = new UnionStreamIterator(new UnionStream($left, $right, 1, true), new WorkingTableIterator($left), new WorkingTableIterator($right));
        $iterator->init($frame);

        self::assertSame([], $frame->buffered);
        self::assertSame([1], $iterator->read());
        $right->rows = [[3, 4]];
        self::assertSame([3], $iterator->read());
        self::assertNull($iterator->read());
        self::assertSame([3], $frame->buffered);
    }

    public function testInitResetsBothTheOperandPositionAndRetainedRow(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $left = new WorkingTable(1);
        $right = new WorkingTable(1);
        $right->rows = [[2]];
        $iterator = new UnionStreamIterator(new UnionStream($left, $right, 1, false), new WorkingTableIterator($left), new WorkingTableIterator($right));
        $iterator->init($frame);

        self::assertSame([2], $iterator->read());
        self::assertNull($iterator->read());
        $left->rows = [[1]];
        $iterator->init($frame);
        self::assertSame([1], $iterator->read());
        self::assertSame([], $frame->buffered);
    }
}
