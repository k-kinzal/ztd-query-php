<?php

declare(strict_types=1);

namespace Tests\Unit\Registry\Spatial\LambertConformal;

use MySqlMemory\Registry\Spatial\LambertConformal\SingleParallelCoordinates;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(SingleParallelCoordinates::class)]
#[Small]
final class SingleParallelCoordinatesTest extends TestCase
{
    public function testEntriesPreserveTheirAuthorityIdentifiers(): void
    {
        $systems = array_filter(SingleParallelCoordinates::entries(), static fn (array $system): bool => $system[1] === 'EPSG');
        self::assertNotEmpty($systems);
        self::assertSame(array_keys($systems), array_column($systems, 2));
    }
}
