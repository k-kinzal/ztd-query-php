<?php

declare(strict_types=1);

namespace Tests\Unit\Automaton;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Automaton\Lr0Automaton;

#[CoversClass(Lr0Automaton::class)]
#[Small]
final class Lr0AutomatonTest extends TestCase
{
    public function testStateCount(): void
    {
        self::assertSame(2, (new Lr0Automaton([[0], [1]], [[3 => 1], []], [[], [0]]))->stateCount());
    }

    public function testTransition(): void
    {
        $automaton = new Lr0Automaton([[0], [1]], [[3 => 1], []], [[], [0]]);

        self::assertSame(1, $automaton->transition(0, 3));
        self::assertNull($automaton->transition(0, 4));
        self::assertNull($automaton->transition(1, 3));
    }
}
