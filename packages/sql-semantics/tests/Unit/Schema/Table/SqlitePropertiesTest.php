<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Schema\Table\SqliteProperties;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Storage;

#[CoversClass(SqliteProperties::class)]
#[Medium]
final class SqlitePropertiesTest extends TestCase
{
    public function testDialectIsSqlite(): void
    {
        $properties = new SqliteProperties();
        self::assertSame(Dialect::Sqlite, $properties->dialect());
        self::assertFalse($properties->withoutRowId);
        self::assertFalse($properties->strict);
        self::assertFalse($properties->temporary);
    }

    public function testBindsWithoutRowIdStrictAndTemporary(): void
    {
        $properties = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TEMP TABLE t(id INTEGER PRIMARY KEY) WITHOUT ROWID, STRICT')->tables[0]->properties;
        self::assertInstanceOf(SqliteProperties::class, $properties);
        self::assertTrue($properties->withoutRowId);
        self::assertTrue($properties->strict);
        self::assertTrue($properties->temporary);
        self::assertSame('WITHOUT ROWID, STRICT', Storage::table($properties, Dialect::Sqlite)->toString());
    }

    public function testSerializesOnlyDeclaredPolicies(): void
    {
        self::assertSame('WITHOUT ROWID', Storage::table(new SqliteProperties(true), Dialect::Sqlite)->toString());
    }
}
