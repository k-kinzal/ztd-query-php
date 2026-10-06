<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\FrameBoundKind;

#[CoversClass(FrameBoundKind::class)]
#[Small]
final class FrameBoundKindTest extends TestCase
{
    public function testOffsetIsTrueForTheTwoOffsetBounds(): void
    {
        self::assertSame([false, true, false, true, false], array_map(static fn (FrameBoundKind $kind): bool => $kind->offset(), FrameBoundKind::cases()));
    }
}
