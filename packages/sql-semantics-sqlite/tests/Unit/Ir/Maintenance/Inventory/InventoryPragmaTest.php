<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Inventory;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Inventory\InventoryKind;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Inventory\InventoryPragma;

#[CoversClass(InventoryPragma::class)]
#[Small]
final class InventoryPragmaTest extends TestCase
{
    public function testToStringKeepsTheExplicitAccessSchema(): void
    {
        self::assertSame('PRAGMA module_list', (new InventoryPragma(InventoryKind::Modules))->toString());
        self::assertSame('PRAGMA "db ""name""".database_list', (new InventoryPragma(InventoryKind::Databases, new Identifier('db "name"')))->toString());
    }

    public function testProfileIdUsesThePinnedNativeProfile(): void
    {
        self::assertSame('sqlite-3.47.2/utf8/short-column-names/numbered-parameters', (new InventoryPragma(InventoryKind::Functions))->profileId());
    }
}
