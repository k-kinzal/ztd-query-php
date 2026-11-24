<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\FlagPragma;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Inventory\InventoryPragma;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\TableInspection;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\TableListing;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\PragmaLowering;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(PragmaLowering::class)]
#[Small]
final class PragmaLoweringTest extends TestCase
{
    public function testLowerSelectsEachNativePragmaFamily(): void
    {
        $semantics = new Semantics();

        self::assertInstanceOf(FlagPragma::class, $semantics->analyze('PRAGMA automatic_index'));
        self::assertInstanceOf(TableInspection::class, $semantics->analyze('PRAGMA table_info'));
        self::assertInstanceOf(TableListing::class, $semantics->analyze('PRAGMA table_list'));
        self::assertInstanceOf(InventoryPragma::class, $semantics->analyze('PRAGMA pragma_list'));
    }
}
