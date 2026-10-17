<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type\Resolved;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;

#[CoversClass(Coercibility::class)]
#[Small]
final class CoercibilityTest extends TestCase
{
    public function testValuesAreThoseCoercibilityReturns(): void
    {
        self::assertSame([0, 1, 2, 3, 4, 5, 6], array_map(static fn (Coercibility $level): int => $level->value, Coercibility::cases()));
    }
}
