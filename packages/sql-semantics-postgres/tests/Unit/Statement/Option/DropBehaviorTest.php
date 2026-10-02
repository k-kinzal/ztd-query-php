<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Option\DropBehavior;

#[CoversClass(DropBehavior::class)]
#[Small]
final class DropBehaviorTest extends TestCase
{
    public function testCasesSpellTheBehaviors(): void
    {
        self::assertSame(['CASCADE', 'RESTRICT'], array_map(static fn (DropBehavior $behavior): string => $behavior->value, DropBehavior::cases()));
    }
}
