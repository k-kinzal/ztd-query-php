<?php

declare(strict_types=1);

namespace Tests\Unit\Registry\Spatial\TransverseMercator\Frames;

use MySqlMemory\Registry\Spatial\TransverseMercator\Frames\Pulkovo1995Coordinates;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Pulkovo1995Coordinates::class)]
#[Small]
final class Pulkovo1995CoordinatesTest extends TestCase
{
    public function testEntriesPreserveTheirAuthorityIdentifiers(): void
    {
        $systems = array_filter(Pulkovo1995Coordinates::entries(), static fn (array $system): bool => $system[1] === 'EPSG');
        self::assertNotEmpty($systems);
        self::assertSame(array_keys($systems), array_column($systems, 2));
    }
}
