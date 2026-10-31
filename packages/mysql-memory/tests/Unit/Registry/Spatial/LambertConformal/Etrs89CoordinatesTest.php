<?php

declare(strict_types=1);

namespace Tests\Unit\Registry\Spatial\LambertConformal;

use MySqlMemory\Registry\Spatial\LambertConformal\Etrs89Coordinates;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Etrs89Coordinates::class)]
#[Small]
final class Etrs89CoordinatesTest extends TestCase
{
    public function testEntriesPreserveTheirAuthorityIdentifiers(): void
    {
        $systems = array_filter(Etrs89Coordinates::entries(), static fn (array $system): bool => $system[1] === 'EPSG');
        self::assertNotEmpty($systems);
        self::assertSame(array_keys($systems), array_column($systems, 2));
    }
}
