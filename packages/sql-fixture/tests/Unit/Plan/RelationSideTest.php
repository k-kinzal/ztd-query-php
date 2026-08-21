<?php

declare(strict_types=1);

namespace Tests\Unit\Plan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlFixture\Plan\RelationSide;

#[CoversClass(RelationSide::class)]
final class RelationSideTest extends TestCase
{
    #[Test]
    public function hasTwoSides(): void
    {
        self::assertSame([RelationSide::Left, RelationSide::Right], RelationSide::cases());
    }

    #[Test]
    public function oppositeFlipsTheSide(): void
    {
        self::assertSame(RelationSide::Right, RelationSide::Left->opposite());
        self::assertSame(RelationSide::Left, RelationSide::Right->opposite());
    }
}
