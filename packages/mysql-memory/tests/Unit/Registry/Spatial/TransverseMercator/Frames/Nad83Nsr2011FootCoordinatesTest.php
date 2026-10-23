<?php

declare(strict_types=1);

namespace Tests\Unit\Registry\Spatial\TransverseMercator\Frames;

use MySqlMemory\Registry\Spatial\TransverseMercator\Frames\Nad83Nsr2011FootCoordinates;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Nad83Nsr2011FootCoordinates::class)]
#[Small]
final class Nad83Nsr2011FootCoordinatesTest extends TestCase
{
    public function testEntriesPreserveTheirAuthorityIdentifiers(): void
    {
        $systems = array_filter(Nad83Nsr2011FootCoordinates::entries(), static fn (array $system): bool => $system[1] === 'EPSG');
        self::assertNotEmpty($systems);
        self::assertSame(array_keys($systems), array_column($systems, 2));
    }
}
