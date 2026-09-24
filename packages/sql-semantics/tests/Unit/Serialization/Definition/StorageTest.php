<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\CreateTableStatement;
use SqlSemantics\Schema\Table\MySqlProperties;
use SqlSemantics\Schema\Table\PostgreSqlProperties;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Storage;

#[CoversClass(Storage::class)]
#[Medium]
final class StorageTest extends TestCase
{
    public function testParametersOmitTheValueOfAnOptionEnabledByName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TABLE u (a INT) WITH (fillfactor = 70, autovacuum_enabled)');
        self::assertInstanceOf(CreateTableStatement::class, $statement);
        self::assertInstanceOf(PostgreSqlProperties::class, $statement->definition->table->properties);
        self::assertSame('"fillfactor" = 70, "autovacuum_enabled"', Storage::parameters($statement->definition->table->properties->storageParameters, Dialect::PostgreSql)->toString());
    }

    public function testTableWritesEachDialectsSuffix(): void
    {
        $postgres = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TEMP TABLE u (a INT) USING heap WITH (fillfactor = 70) ON COMMIT DROP TABLESPACE ts');
        self::assertInstanceOf(CreateTableStatement::class, $postgres);
        self::assertSame('USING "heap" WITH ("fillfactor" = 70) ON COMMIT DROP TABLESPACE "ts"', Storage::table($postgres->definition->table->properties, Dialect::PostgreSql)->toString());
        $sqlite = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('CREATE TABLE u (a INT) WITHOUT ROWID, STRICT');
        self::assertInstanceOf(CreateTableStatement::class, $sqlite);
        self::assertSame('WITHOUT ROWID, STRICT', Storage::table($sqlite->definition->table->properties, Dialect::Sqlite)->toString());
        self::assertSame('', Storage::table(null, Dialect::Sqlite)->toString());
    }

    public function testMysqlWritesOnlyDeclaredOptions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE TABLE u (a INT) ENGINE=InnoDB AUTO_INCREMENT=5 COMMENT='x'");
        self::assertInstanceOf(CreateTableStatement::class, $statement);
        self::assertInstanceOf(MySqlProperties::class, $statement->definition->table->properties);
        self::assertSame("ENGINE `InnoDB` COMMENT = 'x' AUTO_INCREMENT = 5", Storage::mysql($statement->definition->table->properties, Dialect::MySql)->toString());
        self::assertSame('', Storage::mysql(new MySqlProperties(), Dialect::MySql)->toString());
    }

    public function testPartitioningWritesColumnKeysBareAndExpressionKeysParenthesized(): void
    {
        $properties = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE p(id INTEGER, t TEXT) PARTITION BY HASH (id COLLATE "C" int4_ops, (id * 2)) TABLESPACE ts')->tables[0]->properties;
        self::assertInstanceOf(PostgreSqlProperties::class, $properties);
        self::assertNotNull($properties->partitioning);
        self::assertSame('PARTITION BY HASH("id" COLLATE "C" "int4_ops", (("id" * 2)))', Storage::partitioning($properties->partitioning, Dialect::PostgreSql)->toString());
        self::assertSame('PARTITION BY HASH("id" COLLATE "C" "int4_ops", (("id" * 2))) TABLESPACE "ts"', Storage::table($properties, Dialect::PostgreSql)->toString());
    }

    public function testTableWritesInheritsBeforeOtherProperties(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE a(x INTEGER)')))->bind('CREATE TABLE u (b INT) INHERITS (a) TABLESPACE ts');
        self::assertInstanceOf(CreateTableStatement::class, $statement);
        self::assertSame('INHERITS("a") TABLESPACE "ts"', Storage::table($statement->definition->table->properties, Dialect::PostgreSql)->toString());
    }
}
