<?php

declare(strict_types=1);

namespace Tests\Unit\Registry\Spatial\TransverseMercator\Ellipsoids;

use MySqlMemory\Registry\Spatial\TransverseMercator\Ellipsoids\Grs1980Nad83Coordinates;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Grs1980Nad83Coordinates::class)]
#[Small]
final class Grs1980Nad83CoordinatesTest extends TestCase
{
    public function testEntriesPreserveTheirAuthorityIdentifiers(): void
    {
        $systems = array_filter(Grs1980Nad83Coordinates::entries(), static fn (array $system): bool => $system[1] === 'EPSG');
        self::assertNotEmpty($systems);
        self::assertSame(array_keys($systems), array_column($systems, 2));
    }
}
