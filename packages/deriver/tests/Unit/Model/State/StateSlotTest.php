<?php

declare(strict_types=1);

namespace Tests\Unit\Model\State;

use Deriver\Model\State\StateSlot;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Model\State\StateSlot
 */
#[CoversClass(StateSlot::class)]
#[UsesClass(Term::class)]
#[Small]
final class StateSlotTest extends TestCase
{
    public function testPoliciesAreExplicitAndImmutable(): void
    {
        $slot = new StateSlot('example.slot', 'int', Term::constant(1), 'reset', 'preserve');
        self::assertSame('int', $slot->type);
        self::assertSame('reset', $slot->clone);
        self::assertSame('preserve', $slot->invalidation);
    }
}
