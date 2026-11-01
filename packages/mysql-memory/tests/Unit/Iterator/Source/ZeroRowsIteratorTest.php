<?php

declare(strict_types=1);

namespace Tests\Unit\Iterator\Source;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Instance;
use MySqlMemory\Iterator\Source\ZeroRowsIterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ZeroRowsIterator::class)]
#[Small]
final class ZeroRowsIteratorTest extends TestCase
{
    public function testReadAnswersNull(): void
    {
        $session = (new Instance())->connect();
        $iterator = new ZeroRowsIterator();
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertNull($iterator->read());
    }

    public function testInitLeavesTheFrameRowUntouched(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0), [1, 'a']);
        (new ZeroRowsIterator())->init($frame);

        self::assertSame([1, 'a'], $frame->row);
    }
}
