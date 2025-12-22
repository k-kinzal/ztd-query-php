<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\MySqlSchemaParser as PlatformMySqlSchemaParser;
use SqlFixture\Schema\MySqlSchemaParser;
use SqlFixture\Schema\SchemaParseException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\TableSchema;

#[CoversClass(MySqlSchemaParser::class)]
#[UsesClass(ColumnDefinition::class)]
#[UsesClass(TableSchema::class)]
#[UsesClass(SchemaParseException::class)]
#[UsesClass(PlatformMySqlSchemaParser::class)]
final class MySqlSchemaParserTest extends TestCase
{
    #[Test]
    public function parseSimpleTable(): void
    {
        $sql = 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertSame('users', $schema->tableName);
        self::assertCount(2, $schema->columns);
        self::assertArrayHasKey('id', $schema->columns);
        self::assertArrayHasKey('name', $schema->columns);
        self::assertSame(['id'], $schema->primaryKeys);
    }

    #[Test]
    public function parseColumnTypes(): void
    {
        $sql = <<<'SQL'
            CREATE TABLE test (
                col_int INT,
                col_varchar VARCHAR(100),
                col_decimal DECIMAL(10,2)
            )
            SQL;

        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertSame('INT', $schema->columns['col_int']->type);
        self::assertSame('VARCHAR', $schema->columns['col_varchar']->type);
        self::assertSame(100, $schema->columns['col_varchar']->length);
        self::assertSame('DECIMAL', $schema->columns['col_decimal']->type);
        self::assertSame(10, $schema->columns['col_decimal']->precision);
        self::assertSame(2, $schema->columns['col_decimal']->scale);
    }

    #[Test]
    public function parseNotNullConstraint(): void
    {
        $sql = 'CREATE TABLE test (id INT NOT NULL, name VARCHAR(255))';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertFalse($schema->columns['id']->nullable);
        self::assertTrue($schema->columns['name']->nullable);
    }

    #[Test]
    public function parseUnsigned(): void
    {
        $sql = 'CREATE TABLE test (id INT UNSIGNED, age TINYINT UNSIGNED)';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertTrue($schema->columns['id']->unsigned);
        self::assertTrue($schema->columns['age']->unsigned);
    }

    #[Test]
    public function parseAutoIncrement(): void
    {
        $sql = 'CREATE TABLE test (id INT PRIMARY KEY AUTO_INCREMENT)';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertTrue($schema->columns['id']->autoIncrement);
    }

    #[Test]
    public function parseEnum(): void
    {
        $sql = "CREATE TABLE test (status ENUM('active','inactive','pending'))";
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertSame('ENUM', $schema->columns['status']->type);
        self::assertSame(['active', 'inactive', 'pending'], $schema->columns['status']->enumValues);
    }

    #[Test]
    public function parseSet(): void
    {
        $sql = "CREATE TABLE test (permissions SET('read','write','delete'))";
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertSame('SET', $schema->columns['permissions']->type);
        self::assertSame(['read', 'write', 'delete'], $schema->columns['permissions']->enumValues);
    }

    #[Test]
    public function parseCompositePrimaryKey(): void
    {
        $sql = 'CREATE TABLE test (a INT, b INT, PRIMARY KEY (a, b))';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertSame(['a', 'b'], $schema->primaryKeys);
    }

    #[Test]
    public function parsePrimaryKeyImpliesNotNull(): void
    {
        $sql = 'CREATE TABLE test (id INT PRIMARY KEY)';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertFalse($schema->columns['id']->nullable);
    }

    #[Test]
    public function throwsExceptionForInvalidSql(): void
    {
        $this->expectException(SchemaParseException::class);
        (new MySqlSchemaParser())->parse('NOT A VALID SQL STATEMENT');
    }

    #[Test]
    public function throwsExceptionForNonCreateTable(): void
    {
        $this->expectException(SchemaParseException::class);
        (new MySqlSchemaParser())->parse('SELECT * FROM users');
    }

    #[Test]
    public function parseDefaultString(): void
    {
        $sql = "CREATE TABLE test (name VARCHAR(255) DEFAULT 'default_value')";
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertSame('default_value', $schema->columns['name']->default);
    }

    #[Test]
    public function parseDefaultNull(): void
    {
        $sql = 'CREATE TABLE test (name VARCHAR(255) DEFAULT NULL)';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertNull($schema->columns['name']->default);
    }

    #[Test]
    public function parseDefaultInteger(): void
    {
        $sql = 'CREATE TABLE test (count INT DEFAULT 42)';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertSame(42, $schema->columns['count']->default);
    }

    #[Test]
    public function parseDecimalPrecisionAndScale(): void
    {
        $sql = 'CREATE TABLE test (price DECIMAL(10,2))';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertSame(10, $schema->columns['price']->precision);
        self::assertSame(2, $schema->columns['price']->scale);
    }

    #[Test]
    public function parseBitLength(): void
    {
        $sql = 'CREATE TABLE test (flags BIT(8))';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertSame(8, $schema->columns['flags']->length);
    }

    #[Test]
    public function parseTableWithQuotedNames(): void
    {
        $sql = 'CREATE TABLE `my_table` (`my_column` INT PRIMARY KEY)';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertSame('my_table', $schema->tableName);
        self::assertArrayHasKey('my_column', $schema->columns);
    }

    #[Test]
    public function parseDefaultBoolean(): void
    {
        $sql = 'CREATE TABLE test (active TINYINT(1) DEFAULT TRUE)';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertTrue($schema->columns['active']->default);
    }

    #[Test]
    public function parseDefaultFalseBoolean(): void
    {
        $sql = 'CREATE TABLE test (active TINYINT(1) DEFAULT FALSE)';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertFalse($schema->columns['active']->default);
    }

    #[Test]
    public function parseDefaultFloat(): void
    {
        $sql = 'CREATE TABLE test (price DECIMAL(10,2) DEFAULT 9.99)';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertSame(9.99, $schema->columns['price']->default);
    }

    #[Test]
    public function parseGeneratedColumn(): void
    {
        $sql = 'CREATE TABLE test (a INT, b INT, c INT GENERATED ALWAYS AS (a + b) STORED)';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertTrue($schema->columns['c']->generated);
        self::assertFalse($schema->columns['a']->generated);
    }

    #[Test]
    public function parseJsonType(): void
    {
        $sql = 'CREATE TABLE test (data JSON)';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertSame('JSON', $schema->columns['data']->type);
        self::assertTrue($schema->columns['data']->nullable);
    }

    #[Test]
    public function parseSpatialTypes(): void
    {
        $sql = <<<'SQL'
            CREATE TABLE geo (
                col_point POINT,
                col_linestring LINESTRING,
                col_polygon POLYGON,
                col_multipoint MULTIPOINT,
                col_geometry GEOMETRY,
                col_geometrycollection GEOMETRYCOLLECTION
            )
            SQL;

        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertSame('POINT', $schema->columns['col_point']->type);
        self::assertSame('LINESTRING', $schema->columns['col_linestring']->type);
        self::assertSame('POLYGON', $schema->columns['col_polygon']->type);
        self::assertSame('MULTIPOINT', $schema->columns['col_multipoint']->type);
        self::assertSame('GEOMETRY', $schema->columns['col_geometry']->type);
        self::assertSame('GEOMETRYCOLLECTION', $schema->columns['col_geometrycollection']->type);
    }

    #[Test]
    public function parseBinaryTypes(): void
    {
        $sql = <<<'SQL'
            CREATE TABLE bins (
                col_binary BINARY(16),
                col_varbinary VARBINARY(255),
                col_blob BLOB,
                col_tinyblob TINYBLOB,
                col_mediumblob MEDIUMBLOB,
                col_longblob LONGBLOB
            )
            SQL;

        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertSame('BINARY', $schema->columns['col_binary']->type);
        self::assertSame(16, $schema->columns['col_binary']->length);
        self::assertSame('VARBINARY', $schema->columns['col_varbinary']->type);
        self::assertSame(255, $schema->columns['col_varbinary']->length);
        self::assertSame('BLOB', $schema->columns['col_blob']->type);
    }

    #[Test]
    public function parseMultipleDataTypes(): void
    {
        $sql = <<<'SQL'
            CREATE TABLE all_types (
                col_tinyint TINYINT,
                col_smallint SMALLINT,
                col_mediumint MEDIUMINT,
                col_bigint BIGINT,
                col_float FLOAT,
                col_double DOUBLE,
                col_date DATE,
                col_time TIME,
                col_datetime DATETIME,
                col_timestamp TIMESTAMP NULL,
                col_year YEAR,
                col_json JSON,
                col_point POINT,
                col_polygon POLYGON
            )
            SQL;

        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertSame('TINYINT', $schema->columns['col_tinyint']->type);
        self::assertSame('SMALLINT', $schema->columns['col_smallint']->type);
        self::assertSame('MEDIUMINT', $schema->columns['col_mediumint']->type);
        self::assertSame('BIGINT', $schema->columns['col_bigint']->type);
        self::assertSame('FLOAT', $schema->columns['col_float']->type);
        self::assertSame('DOUBLE', $schema->columns['col_double']->type);
        self::assertSame('DATE', $schema->columns['col_date']->type);
        self::assertSame('TIME', $schema->columns['col_time']->type);
        self::assertSame('DATETIME', $schema->columns['col_datetime']->type);
        self::assertSame('TIMESTAMP', $schema->columns['col_timestamp']->type);
        self::assertSame('YEAR', $schema->columns['col_year']->type);
        self::assertSame('JSON', $schema->columns['col_json']->type);
        self::assertSame('POINT', $schema->columns['col_point']->type);
        self::assertSame('POLYGON', $schema->columns['col_polygon']->type);
    }
}
