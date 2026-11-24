<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Vacuum;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Vacuum\Vacuum;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Vacuum\VacuumLowering;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Vacuum\VacuumRegistrations;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(VacuumLowering::class)]
#[Small]
final class VacuumLoweringTest extends TestCase
{
    public function testLowerPreservesMarkersWithoutResolvingTheTemporaryFilename(): void
    {
        $value = (new Semantics())->analyze('VACUUM "TEMP" INTO (missing + ?002)');

        self::assertInstanceOf(Vacuum::class, $value);
        self::assertInstanceOf(VacuumRegistrations::class, $value->destination);
        self::assertSame('002', $value->destination->bindings[0]->label);
        self::assertSame('VACUUM "temp" INTO (0 IN (?002))', $value->toString());
    }
}
