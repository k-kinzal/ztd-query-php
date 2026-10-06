<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Window;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind;

#[CoversClass(FrameBoundKind::class)]
#[Small]
final class FrameBoundKindTest extends TestCase
{
    public function testOffsetTellsWhetherTheBoundaryHasAnOffset(): void
    {
        self::assertTrue(FrameBoundKind::Following->offset());
        self::assertFalse(FrameBoundKind::UnboundedFollowing->offset());
    }
}
