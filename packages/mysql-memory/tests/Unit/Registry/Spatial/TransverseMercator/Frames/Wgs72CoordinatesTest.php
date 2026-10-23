<?php

declare(strict_types=1);

namespace Tests\Unit\Registry\Spatial\TransverseMercator\Frames;

use MySqlMemory\Registry\Spatial\TransverseMercator\Frames\Wgs72Coordinates;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Wgs72Coordinates::class)]
#[Small]
final class Wgs72CoordinatesTest extends TestCase
{
    public function testEntriesPreserveTheirAuthorityIdentifiers(): void
    {
        $systems = array_filter(Wgs72Coordinates::entries(), static fn (array $system): bool => $system[1] === 'EPSG');
        self::assertNotEmpty($systems);
        self::assertSame(array_keys($systems), array_column($systems, 2));
    }
}
