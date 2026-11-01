<?php

declare(strict_types=1);

namespace Tests\Unit\Registry\Spatial;

use MySqlMemory\Registry\Spatial\InstalledCoordinates;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;

#[CoversClass(InstalledCoordinates::class)]
#[Small]
final class InstalledCoordinatesTest extends TestCase
{
    public function testOfRetainsBuiltInGeographicAndProjectedCoordinates(): void
    {
        $systems = InstalledCoordinates::of(GrammarRelease::MySql847);
        self::assertSame('WGS 84', $systems[4326][0]);
        self::assertSame('JGD2000 / Japan Plane Rectangular CS IX', $systems[2451][0]);
        self::assertSame(['EPSG', 2451], [$systems[2451][1], $systems[2451][2]]);
    }
}
