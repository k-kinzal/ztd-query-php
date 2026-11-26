<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Inventory;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Inventory\InventoryKind;

#[CoversClass(InventoryKind::class)]
#[Small]
final class InventoryKindTest extends TestCase
{
    public function testCasesIdentifyTheNativeRegistries(): void
    {
        self::assertSame(
            ['database_list', 'collation_list', 'compile_options', 'function_list', 'module_list', 'pragma_list'],
            array_column(InventoryKind::cases(), 'value'),
        );
    }
}
