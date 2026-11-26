<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Analyze;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\FlagPragma;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\MaintenanceLowering;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Reindex;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(MaintenanceLowering::class)]
#[Small]
final class MaintenanceLoweringTest extends TestCase
{
    public function testLowerPublishesEachResolvedMaintenanceRole(): void
    {
        $semantics = new Semantics();

        self::assertInstanceOf(Analyze::class, $semantics->analyze('ANALYZE'));
        self::assertInstanceOf(Reindex::class, $semantics->analyze('REINDEX'));
        self::assertInstanceOf(FlagPragma::class, $semantics->analyze('PRAGMA count_changes'));
    }
}
