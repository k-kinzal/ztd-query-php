<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\FrameExclusion;

#[CoversClass(FrameExclusion::class)]
#[Small]
final class FrameExclusionTest extends TestCase
{
    public function testCasesSpellTheExclusions(): void
    {
        self::assertSame(['CURRENT ROW', 'GROUP', 'TIES'], array_map(static fn (FrameExclusion $exclusion): string => $exclusion->value, FrameExclusion::cases()));
    }
}
