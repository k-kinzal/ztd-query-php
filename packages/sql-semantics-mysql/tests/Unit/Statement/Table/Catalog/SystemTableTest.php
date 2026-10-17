<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemTable;
use SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemTables;

#[CoversClass(SystemTable::class)]
#[Small]
final class SystemTableTest extends TestCase
{
    public function testColumnFindsAColumnInAnyCase(): void
    {
        $table = SystemTables::of(GrammarRelease::MySql847)->find('information_schema', 'SCHEMATA');

        self::assertNotNull($table);
        self::assertSame('SCHEMA_NAME', $table->column('schema_name')?->name);
        self::assertNull($table->column('missing'));
    }

    public function testSystemTableHoldsHowInformationSchemaListsTheTable(): void
    {
        $table = SystemTables::of(GrammarRelease::MySql847)->find('mysql', 'user');

        self::assertSame(['BASE TABLE', 'InnoDB', 'utf8mb3_bin', 'Users and global privileges'], [$table?->type, $table?->engine, $table?->collation, $table?->comment]);
    }
}
