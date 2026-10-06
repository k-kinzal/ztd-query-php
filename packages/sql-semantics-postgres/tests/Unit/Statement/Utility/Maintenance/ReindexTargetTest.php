<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\ReindexTarget;

#[CoversClass(ReindexTarget::class)]
#[Small]
final class ReindexTargetTest extends TestCase
{
    public function testRelationHoldsForIndexAndTableOnly(): void
    {
        self::assertSame([true, true, false, false, false], array_map(static fn (ReindexTarget $target): bool => $target->relation(), ReindexTarget::cases()));
    }
}
