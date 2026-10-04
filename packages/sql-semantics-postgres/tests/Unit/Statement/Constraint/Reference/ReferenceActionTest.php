<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Constraint\Reference;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\Reference\ReferenceAction::class)]
#[Medium]
final class ReferenceActionTest extends TestCase
{
    public function testSetsColumnsIsTrueForSetNullAndSetDefault(): void
    {
        self::assertSame([
          0 => false,
          1 => false,
          2 => false,
          3 => true,
          4 => true,
        ], array_map(static fn ($action): bool => $action->setsColumns(), \SqlSemantics\Platform\PostgreSql\Statement\Constraint\Reference\ReferenceAction::cases()));
    }
}
