<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Metadata;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\MissingMetadataTable;

#[CoversClass(MissingMetadataTable::class)]
#[Small]
final class MissingMetadataTableTest extends TestCase
{
    public function testKeepsTheExplicitLookupName(): void
    {
        $name = new Identifier('');
        self::assertSame($name, (new MissingMetadataTable($name))->name);
    }
}
