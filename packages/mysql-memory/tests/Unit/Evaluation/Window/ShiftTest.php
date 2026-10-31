<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Window;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Evaluation\Window\Shift;
use MySqlMemory\Instance;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Shift::class)]
#[Small]
final class ShiftTest extends TestCase
{
    public function testDomainIsTheDomainOfTheBoundary(): void
    {
        self::assertSame(Domain::double()->kind, (new Shift(new ColumnRead(Domain::integer(), 0), '1', true, Domain::double()))->domain()->kind);
    }

    public function testEvaluateMovesTheValueInExactOrDoubleArithmetic(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0), [3, null]);

        self::assertSame(['1.5', '4.5', 2.0, null], [
            (new Shift(new ColumnRead(Domain::integer(), 0), '1.5', true, Domain::decimal(65, 30)))->evaluate($frame),
            (new Shift(new ColumnRead(Domain::integer(), 0), '1.5', false, Domain::decimal(65, 30)))->evaluate($frame),
            (new Shift(new ColumnRead(Domain::double(), 0), '1', true, Domain::double()))->evaluate($frame),
            (new Shift(new ColumnRead(Domain::integer(), 1), '1', true, Domain::decimal(65, 30)))->evaluate($frame),
        ]);
    }
}
