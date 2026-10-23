<?php

declare(strict_types=1);

namespace Tests\Unit\Iterator\Source;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Instance;
use MySqlMemory\Iterator\Source\SingleRowIterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(SingleRowIterator::class)]
#[Small]
final class SingleRowIteratorTest extends TestCase
{
    public function testReadAnswersOneEmptyRowAndThenNull(): void
    {
        $session = (new Instance())->connect();
        $iterator = new SingleRowIterator();
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertSame([], $iterator->read());
        self::assertNull($iterator->read());
    }

    public function testInitRestartsTheIteration(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $iterator = new SingleRowIterator();
        $iterator->init($frame);
        $iterator->read();
        $iterator->init($frame);

        self::assertSame([], $iterator->read());
    }
}
