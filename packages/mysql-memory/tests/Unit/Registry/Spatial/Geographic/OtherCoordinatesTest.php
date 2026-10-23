<?php

declare(strict_types=1);

namespace Tests\Unit\Registry\Spatial\Geographic;

use MySqlMemory\Registry\Spatial\Geographic\OtherCoordinates;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(OtherCoordinates::class)]
#[Small]
final class OtherCoordinatesTest extends TestCase
{
    public function testEntriesPreserveTheirAuthorityIdentifiers(): void
    {
        $systems = array_filter(OtherCoordinates::entries(), static fn (array $system): bool => $system[1] === 'EPSG');
        self::assertNotEmpty($systems);
        self::assertSame(array_keys($systems), array_column($systems, 2));
    }
}
