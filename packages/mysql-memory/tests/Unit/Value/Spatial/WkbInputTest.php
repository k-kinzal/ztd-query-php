<?php

declare(strict_types=1);

namespace Tests\Unit\Value\Spatial;

use MySqlMemory\Value\Spatial\Geometry;
use MySqlMemory\Value\Spatial\Wkb;
use MySqlMemory\Value\Spatial\WkbInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(WkbInput::class)]
#[Small]
final class WkbInputTest extends TestCase
{
    public function testReadDistinguishesCompleteWkbAndLegacyGeometryTypedValues(): void
    {
        $reader = new WkbInput();
        $geometry = new Geometry(1, [[1.0, 2.0]]);
        $bytes = Wkb::body($geometry);
        self::assertEquals($geometry, $reader->read($bytes));
        self::assertNull($reader->read($bytes . 'tail'));
        self::assertEquals($geometry, $reader->read(substr_replace($bytes, "\2", 0, 1) . 'tail', true));
        self::assertEquals($geometry, $reader->read(Wkb::write($geometry), internal: true));
    }

    public function testWellFormedRequiresClosedRingsButRetainsBinaryNonfinitePoints(): void
    {
        $reader = new WkbInput();
        self::assertTrue($reader->wellFormed(new Geometry(1, [[NAN, INF]]), false));
        self::assertFalse($reader->wellFormed(new Geometry(3, [], [new Geometry(2, [[0.0, 0.0], [1.0, 0.0], [1.0, 1.0], [2.0, 2.0]])]), false));
        self::assertTrue($reader->wellFormed(new Geometry(3, [], [new Geometry(2, [[0.0, 0.0]])]), true));
    }
}
