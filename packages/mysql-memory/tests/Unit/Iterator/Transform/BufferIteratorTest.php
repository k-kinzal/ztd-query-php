<?php

declare(strict_types=1);

namespace Tests\Unit\Iterator\Transform;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\Assignment;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Instance;
use MySqlMemory\Iterator\Builder;
use MySqlMemory\Iterator\Transform\BufferIterator;
use MySqlMemory\Plan\Path\Source\WorkingTable;
use MySqlMemory\Plan\Path\Transform\Buffer;
use MySqlMemory\Plan\Path\Transform\Project;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(BufferIterator::class)]
#[Small]
final class BufferIteratorTest extends TestCase
{
    public function testInitCompletesInputAssignmentsBeforePublishingAnyRow(): void
    {
        $session = (new Instance())->connect();
        $source = new WorkingTable(1);
        $source->rows = [[2], [1]];
        $assignment = new Assignment('x', new ColumnRead(Domain::integer(), 0), Domain::integer(), Domain::integer());
        $iterator = (new Builder())->build(new Buffer(new Project($source, [$assignment])));
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertSame(1, $session->variables->user('x')[0]);
        self::assertSame([2], $iterator->read());
        self::assertSame(1, $session->variables->user('x')[0]);
        self::assertSame([1], $iterator->read());
        self::assertNull($iterator->read());
    }

    public function testReadRetainsInputOrderAndResetsOnTheNextInitialization(): void
    {
        $session = (new Instance())->connect();
        $source = new WorkingTable(1);
        $source->rows = [[3], [null], [1]];
        $iterator = (new Builder())->build(new Buffer($source));
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $iterator->init($frame);

        self::assertSame([[3], [null], [1]], [$iterator->read(), $iterator->read(), $iterator->read()]);
        self::assertSame([1], $frame->buffered);
        self::assertNull($iterator->read());
        $source->rows = [[4]];
        $iterator->init($frame);
        self::assertSame([4], $iterator->read());
        self::assertNull($iterator->read());
    }
}
