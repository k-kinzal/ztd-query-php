<?php

declare(strict_types=1);

namespace Tests\Unit\Automaton;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Automaton\ResolvedState;

#[CoversClass(ResolvedState::class)]
#[Small]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlParser\Parser\AlternativeParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlParser\Parser\ParseBranch::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlParser\Table\AlternativeCodec::class)]
final class ResolvedStateTest extends TestCase
{
    public function testPropertiesAreKept(): void
    {
        $state = new ResolvedState([1 => 4, 2 => -2], [1 => 1], 2, 1);

        self::assertSame([1 => 4, 2 => -2], $state->actions);
        self::assertSame([1 => 1], $state->reductionCounts);
        self::assertSame(2, $state->shiftReduceConflicts);
        self::assertSame(1, $state->reduceReduceConflicts);
    }
}
