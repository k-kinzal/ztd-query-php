<?php

declare(strict_types=1);

namespace Tests\Unit\Registry\Spatial;

use MySqlMemory\Registry\Spatial\InstalledCoordinates;
use MySqlMemory\Registry\Spatial\LegacyCoordinates;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;

#[CoversClass(LegacyCoordinates::class)]
#[Small]
final class LegacyCoordinatesTest extends TestCase
{
    public function testChangesPreserveTheLegacyKrovakDefinition(): void
    {
        $older = InstalledCoordinates::of(GrammarRelease::MySql8044);
        $current = InstalledCoordinates::of(GrammarRelease::MySql847);
        self::assertSame('S-JTSK (Ferro) / Krovak', $older[2065][0]);
        self::assertNotSame($current[2065][3], $older[2065][3]);
    }
}
