<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Schema\Table\MySqlProperties;
use SqlSemantics\Schema\Table\PostgreSqlProperties;
use SqlSemantics\Schema\Table\Properties;
use SqlSemantics\Schema\Table\SqliteProperties;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Properties::class)]
#[Medium]
final class PropertiesTest extends TestCase
{
    public function testDialectIdentifiesTheOwnerOfEachImplementation(): void
    {
        $forms = [new MySqlProperties(), new PostgreSqlProperties(), new SqliteProperties()];
        self::assertContainsOnlyInstancesOf(Properties::class, $forms);
        self::assertSame([Dialect::MySql, Dialect::PostgreSql, Dialect::Sqlite], array_map(static fn (Properties $properties): Dialect => $properties->dialect(), $forms));
    }

    public function testBoundTablePropertiesMatchTheSchemaDialect(): void
    {
        $mysql = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')->tables[0]->properties;
        $postgres = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')->tables[0]->properties;
        $sqlite = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER)')->tables[0]->properties;
        self::assertSame(Dialect::MySql, $mysql?->dialect());
        self::assertSame(Dialect::PostgreSql, $postgres?->dialect());
        self::assertSame(Dialect::Sqlite, $sqlite?->dialect());
    }
}
