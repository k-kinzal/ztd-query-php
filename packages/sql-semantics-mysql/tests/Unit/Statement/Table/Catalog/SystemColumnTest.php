<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemColumn;
use SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemTables;

#[CoversClass(SystemColumn::class)]
#[Small]
final class SystemColumnTest extends TestCase
{
    public function testSystemColumnHoldsTheMetadataAResultReports(): void
    {
        $engine = SystemTables::of(GrammarRelease::MySql847)->find('information_schema', 'TABLES')?->column('ENGINE');
        $name = SystemTables::of(GrammarRelease::MySql847)->find('information_schema', 'TABLES')?->column('TABLE_NAME');

        self::assertSame(['', 'TABLES', true, 0], [$engine?->database, $engine?->originalTable, $engine?->nullable, $engine?->flags]);
        self::assertSame(['information_schema', 'utf8mb3_bin', false, 20609], [$name?->database, $name?->domain->collation->name, $name?->nullable, $name?->flags]);
    }

    public function testSystemColumnHoldsHowInformationSchemaListsTheColumn(): void
    {
        $column = SystemTables::of(GrammarRelease::MySql847)->find('mysql', 'db')?->column('Select_priv');

        self::assertSame(["enum('N','Y')", 'N', '', 'utf8mb3_general_ci', false], [$column?->columnType, $column?->default, $column?->key, $column?->collation, $column?->listedNullable]);
    }
}
