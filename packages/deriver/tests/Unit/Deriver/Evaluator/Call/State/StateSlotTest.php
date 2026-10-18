<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Call\State;

use Deriver\Deriver\Evaluator\Call\State\StateSlot;
use Deriver\Deriver\Evaluator\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Deriver\Evaluator\Call\State\StateSlot
 */
#[CoversClass(StateSlot::class)]
#[UsesClass(Term::class)]
#[Small]
final class StateSlotTest extends TestCase
{
    public function testPoliciesAreExplicitAndImmutable(): void
    {
        $slot = new StateSlot('example.slot', 'int', Term::constant(1), \Deriver\Deriver\Evaluator\Call\State\ClonePolicy::Reset, \Deriver\Deriver\Evaluator\Call\State\InvalidationPolicy::Preserve);
        self::assertSame('int', $slot->type);
        self::assertSame(\Deriver\Deriver\Evaluator\Call\State\ClonePolicy::Reset, $slot->clone);
        self::assertSame(\Deriver\Deriver\Evaluator\Call\State\InvalidationPolicy::Preserve, $slot->invalidation);
    }
}
