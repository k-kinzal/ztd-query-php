<?php

declare(strict_types=1);

namespace Tests\Unit\Iterator\Source;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Instance;
use MySqlMemory\Iterator\Source\InlineIterator;
use MySqlMemory\Plan\Path\Source\Inline;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(InlineIterator::class)]
#[Small]
final class InlineIteratorTest extends TestCase
{
    public function testReadEvaluatesTheExpressionsOfEachRow(): void
    {
        $session = (new Instance())->connect();
        $text = Domain::string(1, Collation::known('utf8mb4_0900_ai_ci'));
        $iterator = new InlineIterator(new Inline([
            [new Constant(Domain::integer(), 1), new Constant($text, 'a')],
            [new Constant(Domain::integer(), 2), new Constant(Domain::null(), null)],
        ], 2));
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertSame([1, 'a'], $iterator->read());
        self::assertSame([2, null], $iterator->read());
        self::assertNull($iterator->read());
    }

    public function testInitRestartsAtTheFirstRowInTheNewFrame(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $iterator = new InlineIterator(new Inline([[new ColumnRead(Domain::integer(), 0, 1)]], 1));
        $iterator->init(new Frame($context, [], new Frame($context, [7])));
        $first = $iterator->read();
        $iterator->init(new Frame($context, [], new Frame($context, [8])));

        self::assertSame([7], $first);
        self::assertSame([8], $iterator->read());
        self::assertNull($iterator->read());
    }
}
