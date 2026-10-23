<?php

declare(strict_types=1);

namespace Tests\Unit\Registry\Spatial\Geographic;

use MySqlMemory\Registry\Spatial\Geographic\WgsCoordinates;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(WgsCoordinates::class)]
#[Small]
final class WgsCoordinatesTest extends TestCase
{
    public function testEntriesPreserveTheirAuthorityIdentifiers(): void
    {
        $systems = array_filter(WgsCoordinates::entries(), static fn (array $system): bool => $system[1] === 'EPSG');
        self::assertNotEmpty($systems);
        self::assertSame(array_keys($systems), array_column($systems, 2));
    }
}
