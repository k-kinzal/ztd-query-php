<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Coverage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Coverage\SequenceObservation;
use SqlFaker\Grammar\Generation\Token\ProductionOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

#[CoversClass(SequenceObservation::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class SequenceObservationTest extends TestCase
{
    public function testLeavesGroupsCompleteIdentitiesUnderEveryAncestor(): void
    {
        $leaf = new TerminalOccurrence('A', 2, [0, 1], ['root', 'child']);
        self::assertSame([0 => [[2, 'A']], 1 => [[2, 'A']]], (new SequenceObservation())->leaves([$leaf]));
        self::assertSame([], (new SequenceObservation())->leaves([]));
    }

    public function testPreservedCreditsUnmodifiedSiblingsAndTheirEmptyChildren(): void
    {
        $a = new TerminalOccurrence('A', 4, [0, 1], ['root', 'a']);
        $b = new TerminalOccurrence('B', 5, [0, 2], ['root', 'b']);
        $input = new TerminalSequence([$a, $b], [$a, $b], [], [
            new ProductionOccurrence(0, null, 'root', 0), new ProductionOccurrence(1, 0, 'a', 0),
            new ProductionOccurrence(2, 0, 'b', 0), new ProductionOccurrence(3, 2, 'empty', 0),
        ]);
        $observer = new SequenceObservation();
        self::assertSame([0, 1, 2, 3], $observer->preserved($input));
        self::assertSame([2, 3], $observer->preserved($input->replace(0, 1, [$a->replaced('OTHER', 'source')], 'source')));
        self::assertSame([2, 3], $observer->preserved($input->replace(0, 1, [], 'source')));
    }

    public function testPreservedDistinguishesEmptyGrammarFromInsertedOutput(): void
    {
        $input = new TerminalSequence([], [], [], [new ProductionOccurrence(0, null, 'root', 0), new ProductionOccurrence(1, 0, 'empty', 0)]);
        $observer = new SequenceObservation();
        self::assertSame([0, 1], $observer->preserved($input));
        self::assertSame([], $observer->preserved($input->replace(0, 0, [$input->insertedFor('X', 1, 'source')], 'source')));
    }
}
