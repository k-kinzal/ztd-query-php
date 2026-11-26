<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Metadata;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\TableInspectionKind;

#[CoversClass(TableInspectionKind::class)]
#[Small]
final class TableInspectionKindTest extends TestCase
{
    public function testCasesIdentifyTheFourNativeSingleTableReads(): void
    {
        self::assertSame(['table_info', 'table_xinfo', 'index_list', 'foreign_key_list'], array_column(TableInspectionKind::cases(), 'value'));
    }
}
