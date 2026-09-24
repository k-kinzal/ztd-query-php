<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Routine\Option\NullInputBehavior;

#[CoversClass(NullInputBehavior::class)]
#[Small]
final class NullInputBehaviorTest extends TestCase
{
    public function testSpellsEachBehaviorAsItsKeywords(): void
    {
        self::assertSame(['CALLED ON NULL INPUT', 'STRICT'], array_column(NullInputBehavior::cases(), 'value'));
    }
}
