<?php

declare(strict_types=1);

namespace Tests\Unit\Registry\Spatial\LambertConformal;

use MySqlMemory\Registry\Spatial\LambertConformal\Nad83Nsr2007Coordinates;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Nad83Nsr2007Coordinates::class)]
#[Small]
final class Nad83Nsr2007CoordinatesTest extends TestCase
{
    public function testEntriesPreserveTheirAuthorityIdentifiers(): void
    {
        $systems = array_filter(Nad83Nsr2007Coordinates::entries(), static fn (array $system): bool => $system[1] === 'EPSG');
        self::assertNotEmpty($systems);
        self::assertSame(array_keys($systems), array_column($systems, 2));
    }
}
