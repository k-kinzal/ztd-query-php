<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemTables;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Declaration\RelationKind;

#[CoversClass(SystemTables::class)]
#[Small]
final class SystemTablesTest extends TestCase
{
    public function testOfReadsTheCatalogOfEachRelease(): void
    {
        self::assertSame(SystemTables::of(GrammarRelease::MySql847), SystemTables::of(GrammarRelease::MySql847));
        self::assertNull(SystemTables::of(GrammarRelease::MySql5744)->find('information_schema', 'CHECK_CONSTRAINTS'));
        self::assertNotNull(SystemTables::of(GrammarRelease::MySql847)->find('information_schema', 'CHECK_CONSTRAINTS'));
        self::assertSame(['MEMORY', null], [SystemTables::of(GrammarRelease::MySql5744)->find('information_schema', 'TABLES')?->engine, SystemTables::of(GrammarRelease::MySql847)->find('information_schema', 'TABLES')?->engine]);
    }

    public function testOfFallsBackToTheLatestSeriesOfTheMajorVersion(): void
    {
        self::assertCount(count(SystemTables::of(GrammarRelease::MySql847)->tables), SystemTables::of(GrammarRelease::MySql820)->tables);
        self::assertCount(count(SystemTables::of(GrammarRelease::MySql910)->tables), SystemTables::of(GrammarRelease::MySql901)->tables);
    }

    public function testKeyFoldsTheNamesOfInformationSchemaOnly(): void
    {
        self::assertSame(['information_schema.tables', 'mysql.User'], [SystemTables::key('INFORMATION_SCHEMA', 'Tables'), SystemTables::key('mysql', 'User')]);
    }

    public function testDomainBuildsTheTypeAResultReports(): void
    {
        self::assertSame([Kind::String, Field::Enum, ['NO', 'YES']], [SystemTables::domain(254, 3, 0, 4481, 'utf8mb3_bin', 2, "enum('NO','YES')")->kind, SystemTables::domain(254, 3, 0, 4481, 'utf8mb3_bin', 2, "enum('NO','YES')")->field, SystemTables::domain(254, 3, 0, 4481, 'utf8mb3_bin', 2, "enum('NO','YES')")->members]);
        self::assertSame([Kind::Integer, true], [SystemTables::domain(8, 21, 0, 32928, 'binary', 5, 'bigint unsigned')->kind, SystemTables::domain(8, 21, 0, 32928, 'binary', 5, 'bigint unsigned')->unsigned]);
        self::assertSame([Kind::DateTime, 'latin1_swedish_ci'], [SystemTables::domain(7, 19, 0, 4225, 'latin1_swedish_ci', 5, 'timestamp')->kind, SystemTables::domain(7, 19, 0, 4225, 'latin1_swedish_ci', 5, 'timestamp')->collation->name]);
        self::assertSame([Kind::Json, 'binary'], [SystemTables::domain(245, 4294967295, 0, 144, 'binary', 2, 'json')->kind, SystemTables::domain(245, 4294967295, 0, 144, 'binary', 2, 'json')->collation->name]);
        self::assertSame(Kind::Null, SystemTables::domain(6, 0, 0, 32896, 'binary', 6, 'varbinary(0)')->kind);
    }

    public function testMembersReadsTheMembersOfAnEnumOrSet(): void
    {
        self::assertSame(['a', "b'c", ''], SystemTables::members("set('a','b''c','')"));
    }

    public function testFindIgnoresTheCaseOfInformationSchemaOnly(): void
    {
        $catalog = SystemTables::of(GrammarRelease::MySql847);

        self::assertSame('SCHEMATA', $catalog->find('Information_Schema', 'schemata')?->name);
        self::assertSame('user', $catalog->find('mysql', 'user')?->name);
        self::assertNull($catalog->find('mysql', 'USER'));
        self::assertNull($catalog->find('MYSQL', 'user'));
    }

    public function testDeclarationsDeclareEveryTableWithTheTypesOfItsColumns(): void
    {
        $catalog = SystemTables::of(GrammarRelease::MySql847);
        $tables = $catalog->find('information_schema', 'TABLES');

        self::assertCount(count($catalog->tables), $catalog->declarations());
        self::assertSame([RelationKind::View, 'information_schema', 'TABLE_NAME'], [$tables?->declaration->kind, $tables?->declaration->name->schema?->value, $tables?->declaration->columns[2]->name->value]);
        self::assertSame(RelationKind::BaseTable, $catalog->find('mysql', 'user')?->declaration->kind);
    }
}
