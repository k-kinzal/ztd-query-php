<?php

declare(strict_types=1);

namespace Tests\Unit\Registry\Spatial;

use MySqlMemory\Registry\Spatial\OtherProjectionsCoordinates;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(OtherProjectionsCoordinates::class)]
#[Small]
final class OtherProjectionsCoordinatesTest extends TestCase
{
    public function testEntriesPreserveTheirAuthorityIdentifiers(): void
    {
        $systems = array_filter(OtherProjectionsCoordinates::entries(), static fn (array $system): bool => $system[1] === 'EPSG');
        self::assertNotEmpty($systems);
        self::assertSame(array_keys($systems), array_column($systems, 2));
    }
}
