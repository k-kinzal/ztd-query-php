<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Routine\Option\LeakproofBehavior;

#[CoversClass(LeakproofBehavior::class)]
#[Small]
final class LeakproofBehaviorTest extends TestCase
{
    public function testSpellsEachBehaviorAsItsKeywords(): void
    {
        self::assertSame(['LEAKPROOF', 'NOT LEAKPROOF'], array_column(LeakproofBehavior::cases(), 'value'));
    }
}
