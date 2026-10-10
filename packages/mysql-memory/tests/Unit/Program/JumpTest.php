<?php

declare(strict_types=1);

namespace Tests\Unit\Program;

use MySqlMemory\Program\Flow;
use MySqlMemory\Program\Jump;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;

#[CoversClass(Jump::class)]
#[Small]
final class JumpTest extends TestCase
{
    public function testEndsAnswersWhetherTheJumpLeavesALabeledStatementOrTheBlockOfAnExitHandler(): void
    {
        $block = new Block();

        self::assertSame([true, false, true, false, false], [
            (new Jump(Flow::Leave, 'L'))->ends('l', null),
            (new Jump(Flow::Leave, 'l'))->ends(null, null),
            (new Jump(Flow::Exit, null, $block))->ends(null, $block),
            (new Jump(Flow::Exit, null, $block))->ends(null, new Block()),
            (new Jump(Flow::Iterate, 'l'))->ends('l', null),
        ]);
    }

    public function testRepeatsAnswersWhetherTheJumpStartsALoopAgain(): void
    {
        self::assertSame([true, false, false], [(new Jump(Flow::Iterate, 'l'))->repeats('L'), (new Jump(Flow::Iterate, 'l'))->repeats('m'), (new Jump(Flow::Leave, 'l'))->repeats('l')]);
    }
}
