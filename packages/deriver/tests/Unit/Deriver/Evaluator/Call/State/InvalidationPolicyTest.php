<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Call\State;

use Deriver\Deriver\Evaluator\Call\State\InvalidationPolicy as Subject;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class InvalidationPolicyTest extends TestCase
{
    public function testPreservePoliciesRetainCustomStateSlots(): void
    {
        $state = new \Deriver\Deriver\Evaluator\Execution\State();
        $state->memory->slotContracts['vendor.cache'] = new \Deriver\Deriver\Evaluator\Call\State\StateSlot('vendor.cache', 'int', invalidation: Subject::Preserve);
        $registry = new \Deriver\Deriver\Evaluator\Call\StateRegistry(array_values($state->memory->slotContracts));
        self::assertSame('int:copy:preserve:symbolic', $registry->manifest['slot:vendor.cache']);
        self::assertSame(Subject::Preserve, $registry->slots['vendor.cache']->invalidation);
    }
}
