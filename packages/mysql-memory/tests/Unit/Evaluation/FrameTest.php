<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Frame::class)]
#[Small]
final class FrameTest extends TestCase
{
    public function testOutAnswersTheFrameItselfForDepthZero(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0), [1, 'a']);

        self::assertSame($frame, $frame->out(0));
    }

    public function testOutAnswersTheFrameOfAnEnclosingBlock(): void
    {
        $session = (new Instance())->connect();
        $outermost = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0), [1]);
        $middle = new Frame($outermost->context, [2], $outermost);
        $inner = new Frame($outermost->context, [3], $middle);

        self::assertSame([[2], [1]], [$inner->out(1)->row, $inner->out(2)->row]);
    }

    public function testOutStopsAtTheOutermostFrame(): void
    {
        $session = (new Instance())->connect();
        $outermost = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0), [1]);
        $inner = new Frame($outermost->context, [2], $outermost);

        self::assertSame($outermost, $inner->out(5));
    }

    public function testInnerAnswersAFrameWithAnEmptyRowInsideThisOne(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0), [1, 2]);
        $inner = $frame->inner();

        self::assertSame([[], $frame, $frame->context], [$inner->row, $inner->outer, $inner->context]);
    }
}
