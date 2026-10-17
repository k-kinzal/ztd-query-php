<?php

declare(strict_types=1);

namespace Tests\Unit\Value\Spatial;

use MySqlMemory\Value\Spatial\Bounds;
use MySqlMemory\Value\Spatial\Geometry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Bounds::class)]
#[Small]
final class BoundsTest extends TestCase
{
    public function testOfIncludesEveryNestedComponent(): void
    {
        $geometry = new Geometry(7, [], [new Geometry(7), new Geometry(1, [[2.0, 3.0]]), new Geometry(2, [[-1.0, 4.0], [5.0, 0.0]])]);
        self::assertSame([-1.0, 0.0, 5.0, 4.0], (new Bounds())->of($geometry));
        self::assertNull((new Bounds())->of(new Geometry(7)));
    }

    public function testMergeIncludesBothEnvelopes(): void
    {
        self::assertSame([0.0, -1.0, 4.0, 5.0], (new Bounds())->merge([0.0, 0.0, 2.0, 5.0], [1.0, -1.0, 4.0, 2.0]));
    }

    public function testContainsDistinguishesTheBoundaryAndDegenerateDimensions(): void
    {
        $bounds = new Bounds();
        self::assertTrue($bounds->contains([0.0, 0.0, 2.0, 2.0], [0.0, 1.0, 0.0, 1.0], true));
        self::assertFalse($bounds->contains([0.0, 0.0, 2.0, 2.0], [0.0, 1.0, 0.0, 1.0], false));
        self::assertTrue($bounds->contains([0.0, 0.0, 0.0, 2.0], [0.0, 1.0, 0.0, 1.0], false));
        self::assertTrue($bounds->contains([0.0, 0.0, 0.0, 0.0], [0.0, 0.0, 0.0, 0.0], false));
        self::assertFalse($bounds->contains([0.0, 0.0, 2.0, 2.0], [1.0, 1.0, 3.0, 3.0], true));
    }
}
