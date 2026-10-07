<?php

declare(strict_types=1);

namespace Tests\Unit\Iterator;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Instance;
use MySqlMemory\Iterator\Builder;
use MySqlMemory\Iterator\RowIterator;
use MySqlMemory\Plan\Path\Source\SingleRow;
use MySqlMemory\Plan\Path\Source\WorkingTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(RowIterator::class)]
#[Small]
final class RowIteratorTest extends TestCase
{
    public function testReadAnswersNullOnceTheRowsAreExhausted(): void
    {
        $session = (new Instance())->connect();
        $working = new WorkingTable(1);
        $working->rows = [[1]];
        $iterator = (new Builder())->build($working);
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertSame([[1], null, null], [$iterator->read(), $iterator->read(), $iterator->read()]);
    }

    public function testInitStartsTheIterationAgain(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $iterator = (new Builder())->build(new SingleRow());
        $iterator->init($frame);
        $first = [$iterator->read(), $iterator->read()];
        $iterator->init($frame);

        self::assertSame([[], null], $first);
        self::assertSame([], $iterator->read());
    }
}
