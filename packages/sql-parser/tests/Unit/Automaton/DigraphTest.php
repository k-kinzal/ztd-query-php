<?php

declare(strict_types=1);

namespace Tests\Unit\Automaton;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Automaton\Bitset;
use SqlParser\Automaton\Digraph;

#[CoversClass(Digraph::class)]
#[UsesClass(Bitset::class)]
#[Small]
final class DigraphTest extends TestCase
{
    public function testCloseUnionsAlongEdgesAndAcrossCycles(): void
    {
        $sets = [[1], [2], [4], [8]];
        $closed = (new Digraph())->close(4, [0 => [1], 1 => [2], 2 => [1], 3 => []], $sets);

        self::assertSame([1 | 2 | 4], $closed[0]);
        self::assertSame([2 | 4], $closed[1]);
        self::assertSame([2 | 4], $closed[2]);
        self::assertSame([8], $closed[3]);
    }

    public function testTraverseClosesOnlyWhatTheRootReaches(): void
    {
        $sets = [[1], [2], [4]];
        $marks = [0, 0, 0];
        $stack = [];
        (new Digraph())->traverse(1, [0 => [1], 1 => [2], 2 => []], $sets, $marks, $stack);

        self::assertSame([[1], [6], [4]], $sets);
        self::assertSame(0, $marks[0]);
        self::assertSame(PHP_INT_MAX, $marks[1]);
        self::assertSame([], $stack);
    }
}
