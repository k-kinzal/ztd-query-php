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

}
