<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Metadata;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\CatalogTable;

#[CoversClass(CatalogTable::class)]
#[Small]
final class CatalogTableTest extends TestCase
{
    public function testNameIdentifiesTheNativeCatalogInBothNamespaceKinds(): void
    {
        self::assertSame('sqlite_temp_master', (new CatalogTable(new Identifier('TEMP')))->name()->value);
        self::assertSame('sqlite_master', (new CatalogTable(new Identifier('extension')))->name()->value);
    }
}
