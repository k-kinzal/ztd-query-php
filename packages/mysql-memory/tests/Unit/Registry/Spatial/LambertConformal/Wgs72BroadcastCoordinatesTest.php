<?php

declare(strict_types=1);

namespace Tests\Unit\Registry\Spatial\LambertConformal;

use MySqlMemory\Registry\Spatial\LambertConformal\Wgs72BroadcastCoordinates;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Wgs72BroadcastCoordinates::class)]
#[Small]
final class Wgs72BroadcastCoordinatesTest extends TestCase
{
    public function testEntriesPreserveTheirAuthorityIdentifiers(): void
    {
        $systems = array_filter(Wgs72BroadcastCoordinates::entries(), static fn (array $system): bool => $system[1] === 'EPSG');
        self::assertNotEmpty($systems);
        self::assertSame(array_keys($systems), array_column($systems, 2));
    }
}
