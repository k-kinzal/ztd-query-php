<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\MySqlSchemaParser;
use SqlFixture\Schema\SchemaParseException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\TableSchema;

/**
 * Tests for the platform MySqlSchemaParser (non-deprecated).
 */
#[CoversClass(MySqlSchemaParser::class)]
#[UsesClass(ColumnDefinition::class)]
#[UsesClass(TableSchema::class)]
#[UsesClass(SchemaParseException::class)]
final class MySqlSchemaParserTest extends TestCase
{
    #[Test]
    public function parseSimpleTable(): void
    {
        $sql = 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255) NOT NULL)';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertSame('users', $schema->tableName);
        self::assertCount(2, $schema->columns);
        self::assertSame('INT', $schema->columns['id']->type);
        self::assertSame('VARCHAR', $schema->columns['name']->type);
        self::assertSame(255, $schema->columns['name']->length);
        self::assertFalse($schema->columns['id']->nullable);
        self::assertFalse($schema->columns['name']->nullable);
    }

    #[Test]
    public function parseAllNumericTypes(): void
    {
        $sql = <<<'SQL'
            CREATE TABLE nums (
                col_tinyint TINYINT,
                col_smallint SMALLINT,
                col_mediumint MEDIUMINT,
                col_int INT,
                col_integer INTEGER,
                col_bigint BIGINT,
                col_float FLOAT,
                col_double DOUBLE,
                col_real REAL,
                col_decimal DECIMAL(10,2),
                col_numeric NUMERIC(8,3),
                col_bit BIT(16)
            )
            SQL;

        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertSame('TINYINT', $schema->columns['col_tinyint']->type);
        self::assertSame('SMALLINT', $schema->columns['col_smallint']->type);
        self::assertSame('MEDIUMINT', $schema->columns['col_mediumint']->type);
        self::assertSame('INT', $schema->columns['col_int']->type);
        self::assertSame('INTEGER', $schema->columns['col_integer']->type);
        self::assertSame('BIGINT', $schema->columns['col_bigint']->type);
        self::assertSame('FLOAT', $schema->columns['col_float']->type);
        self::assertSame('DOUBLE', $schema->columns['col_double']->type);
        self::assertSame('REAL', $schema->columns['col_real']->type);
        self::assertSame('DECIMAL', $schema->columns['col_decimal']->type);
        self::assertSame(10, $schema->columns['col_decimal']->precision);
        self::assertSame(2, $schema->columns['col_decimal']->scale);
        self::assertSame('NUMERIC', $schema->columns['col_numeric']->type);
        self::assertSame(8, $schema->columns['col_numeric']->precision);
        self::assertSame(3, $schema->columns['col_numeric']->scale);
        self::assertSame('BIT', $schema->columns['col_bit']->type);
        self::assertSame(16, $schema->columns['col_bit']->length);
    }

    #[Test]
    public function parseAllStringTypes(): void
    {
        $sql = <<<'SQL'
            CREATE TABLE strs (
                col_char CHAR(10),
                col_varchar VARCHAR(255),
                col_tinytext TINYTEXT,
                col_text TEXT,
                col_mediumtext MEDIUMTEXT,
                col_longtext LONGTEXT
            )
            SQL;

        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertSame('CHAR', $schema->columns['col_char']->type);
        self::assertSame(10, $schema->columns['col_char']->length);
        self::assertSame('VARCHAR', $schema->columns['col_varchar']->type);
        self::assertSame(255, $schema->columns['col_varchar']->length);
        self::assertSame('TINYTEXT', $schema->columns['col_tinytext']->type);
        self::assertSame('TEXT', $schema->columns['col_text']->type);
        self::assertSame('MEDIUMTEXT', $schema->columns['col_mediumtext']->type);
        self::assertSame('LONGTEXT', $schema->columns['col_longtext']->type);
    }

    #[Test]
    public function parseUnsignedInTypeOptions(): void
    {
        $sql = 'CREATE TABLE test (id INT UNSIGNED NOT NULL, age TINYINT UNSIGNED)';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertTrue($schema->columns['id']->unsigned);
        self::assertTrue($schema->columns['age']->unsigned);
    }

    #[Test]
    public function parseAutoIncrementWithPrimaryKey(): void
    {
        $sql = 'CREATE TABLE test (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(255))';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertTrue($schema->columns['id']->autoIncrement);
        self::assertFalse($schema->columns['id']->nullable);
        self::assertSame(['id'], $schema->primaryKeys);
    }

    #[Test]
    public function parseEnumValues(): void
    {
        $sql = "CREATE TABLE test (status ENUM('active','inactive','pending') NOT NULL)";
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertSame('ENUM', $schema->columns['status']->type);
        self::assertSame(['active', 'inactive', 'pending'], $schema->columns['status']->enumValues);
    }

    #[Test]
    public function parseSetValues(): void
    {
        $sql = "CREATE TABLE test (perms SET('read','write','delete'))";
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertSame('SET', $schema->columns['perms']->type);
        self::assertSame(['read', 'write', 'delete'], $schema->columns['perms']->enumValues);
    }

    #[Test]
    public function parseCompositePrimaryKeyWithBackticks(): void
    {
        $sql = 'CREATE TABLE test (`a` INT, `b` INT, PRIMARY KEY (`a`, `b`))';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertSame(['a', 'b'], $schema->primaryKeys);
        self::assertFalse($schema->columns['a']->nullable);
        self::assertFalse($schema->columns['b']->nullable);
    }

    #[Test]
    public function throwsOnEmptyInput(): void
    {
        $this->expectException(SchemaParseException::class);
        (new MySqlSchemaParser())->parse('');
    }

    #[Test]
    public function throwsOnNonCreateTableStatement(): void
    {
        $this->expectException(SchemaParseException::class);
        (new MySqlSchemaParser())->parse('INSERT INTO users VALUES (1, "test")');
    }

    #[Test]
    public function parseDefaultStringValue(): void
    {
        $sql = "CREATE TABLE test (name VARCHAR(255) DEFAULT 'hello')";
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertSame('hello', $schema->columns['name']->default);
    }

    #[Test]
    public function parseDefaultIntegerValue(): void
    {
        $sql = 'CREATE TABLE test (count INT DEFAULT 42)';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertSame(42, $schema->columns['count']->default);
    }

    #[Test]
    public function parseDefaultFloatValue(): void
    {
        $sql = 'CREATE TABLE test (price DECIMAL(10,2) DEFAULT 9.99)';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertSame(9.99, $schema->columns['price']->default);
    }

    #[Test]
    public function parseDefaultNullValue(): void
    {
        $sql = 'CREATE TABLE test (name VARCHAR(255) DEFAULT NULL)';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertNull($schema->columns['name']->default);
    }

    #[Test]
    public function parseDefaultBooleanTrueValue(): void
    {
        $sql = 'CREATE TABLE test (active BOOLEAN DEFAULT TRUE)';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertTrue($schema->columns['active']->default);
    }

    #[Test]
    public function parseDefaultBooleanFalseValue(): void
    {
        $sql = 'CREATE TABLE test (active BOOLEAN DEFAULT FALSE)';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertFalse($schema->columns['active']->default);
    }

    #[Test]
    public function parseNoDefaultReturnsNull(): void
    {
        $sql = 'CREATE TABLE test (name VARCHAR(255) NOT NULL)';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertNull($schema->columns['name']->default);
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
    public function parseNullableDefaults(): void
    {
        $sql = 'CREATE TABLE test (id INT NOT NULL, name VARCHAR(255), notes TEXT)';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertFalse($schema->columns['id']->nullable);
        self::assertTrue($schema->columns['name']->nullable);
        self::assertTrue($schema->columns['notes']->nullable);
    }

    #[Test]
    public function parseBacktickedColumnNames(): void
    {
        $sql = 'CREATE TABLE `test` (`my_id` INT PRIMARY KEY, `my_name` VARCHAR(255))';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertSame('test', $schema->tableName);
        self::assertArrayHasKey('my_id', $schema->columns);
        self::assertArrayHasKey('my_name', $schema->columns);
    }

    #[Test]
    public function parseUnsignedInColumnOptions(): void
    {
        $sql = 'CREATE TABLE test (id INT UNSIGNED PRIMARY KEY)';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertTrue($schema->columns['id']->unsigned);
        self::assertFalse($schema->columns['id']->nullable);
    }

    #[Test]
    public function parseDecimalWithoutParameters(): void
    {
        $sql = 'CREATE TABLE test (amount DECIMAL)';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertSame('DECIMAL', $schema->columns['amount']->type);
    }

    #[Test]
    public function parsePrimaryKeyColumnDefinitionLevel(): void
    {
        $sql = 'CREATE TABLE test (id INT PRIMARY KEY)';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertFalse($schema->columns['id']->nullable);
    }

    #[Test]
    public function parseTableLevelPrimaryKeyMakesColumnsNonNullable(): void
    {
        $sql = 'CREATE TABLE test (a INT, b INT, PRIMARY KEY (a))';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertFalse($schema->columns['a']->nullable);
    }

    #[Test]
    public function parseAutoIncrementWithoutPrimaryKeyInDefinition(): void
    {
        $sql = 'CREATE TABLE test (id INT AUTO_INCREMENT, name VARCHAR(255), PRIMARY KEY (id))';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertTrue($schema->columns['id']->autoIncrement);
    }

    #[Test]
    public function parseDefaultNonStringValue(): void
    {
        $sql = 'CREATE TABLE test (val VARCHAR(255) DEFAULT CURRENT_TIMESTAMP)';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertSame('CURRENT_TIMESTAMP', $schema->columns['val']->default);
    }

    #[Test]
    public function parseBitDefaultLength(): void
    {
        $sql = 'CREATE TABLE test (flag BIT(1))';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertSame('BIT', $schema->columns['flag']->type);
        self::assertSame(1, $schema->columns['flag']->length);
    }

    #[Test]
    public function parseDecimalAllVariants(): void
    {
        $sql = <<<'SQL'
            CREATE TABLE test (
                col_decimal DECIMAL(10,2),
                col_numeric NUMERIC(8,3),
                col_dec DEC(5,1),
                col_fixed FIXED(6,2)
            )
            SQL;

        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertSame('DECIMAL', $schema->columns['col_decimal']->type);
        self::assertSame(10, $schema->columns['col_decimal']->precision);
        self::assertSame(2, $schema->columns['col_decimal']->scale);

        self::assertSame('NUMERIC', $schema->columns['col_numeric']->type);
        self::assertSame(8, $schema->columns['col_numeric']->precision);
        self::assertSame(3, $schema->columns['col_numeric']->scale);

        self::assertSame('DEC', $schema->columns['col_dec']->type);
        self::assertSame(5, $schema->columns['col_dec']->precision);
        self::assertSame(1, $schema->columns['col_dec']->scale);

        self::assertSame('FIXED', $schema->columns['col_fixed']->type);
        self::assertSame(6, $schema->columns['col_fixed']->precision);
        self::assertSame(2, $schema->columns['col_fixed']->scale);
    }

    #[Test]
    public function parseNonAutoIncrementColumn(): void
    {
        $sql = 'CREATE TABLE test (id INT, name VARCHAR(255))';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertFalse($schema->columns['id']->autoIncrement);
        self::assertFalse($schema->columns['name']->autoIncrement);
    }

    #[Test]
    public function parseNonGeneratedColumn(): void
    {
        $sql = 'CREATE TABLE test (id INT, name VARCHAR(255))';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertFalse($schema->columns['id']->generated);
        self::assertFalse($schema->columns['name']->generated);
    }

    #[Test]
    public function parseNonUnsignedColumn(): void
    {
        $sql = 'CREATE TABLE test (id INT, name VARCHAR(255))';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertFalse($schema->columns['id']->unsigned);
    }

    #[Test]
    public function parseLowercaseDefaultNull(): void
    {
        $sql = 'CREATE TABLE test (name VARCHAR(255) DEFAULT null)';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertNull($schema->columns['name']->default);
    }

    #[Test]
    public function parseLowercaseDefaultTrue(): void
    {
        $sql = 'CREATE TABLE test (active BOOLEAN DEFAULT true)';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertTrue($schema->columns['active']->default);
    }

    #[Test]
    public function parseLowercaseDefaultFalse(): void
    {
        $sql = 'CREATE TABLE test (active BOOLEAN DEFAULT false)';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertFalse($schema->columns['active']->default);
    }

    #[Test]
    public function parseLowercaseTypeName(): void
    {
        $sql = 'CREATE TABLE test (id int primary key auto_increment, name varchar(255))';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertSame('INT', $schema->columns['id']->type);
        self::assertSame('VARCHAR', $schema->columns['name']->type);
    }

    #[Test]
    public function parseDecimalWithPrecisionOnly(): void
    {
        $sql = 'CREATE TABLE test (amount DECIMAL(5))';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertSame('DECIMAL', $schema->columns['amount']->type);
        self::assertSame(5, $schema->columns['amount']->precision);
        self::assertSame(0, $schema->columns['amount']->scale);
    }

    #[Test]
    public function parseBitWithoutLength(): void
    {
        $sql = 'CREATE TABLE test (flag BIT)';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertSame('BIT', $schema->columns['flag']->type);
    }

    #[Test]
    public function parseNumericWithPrecisionOnly(): void
    {
        $sql = 'CREATE TABLE test (val NUMERIC(7))';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertSame('NUMERIC', $schema->columns['val']->type);
        self::assertSame(7, $schema->columns['val']->precision);
        self::assertSame(0, $schema->columns['val']->scale);
    }

    #[Test]
    public function parseFixedWithPrecisionOnly(): void
    {
        $sql = 'CREATE TABLE test (val FIXED(4))';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertSame('FIXED', $schema->columns['val']->type);
        self::assertSame(4, $schema->columns['val']->precision);
        self::assertSame(0, $schema->columns['val']->scale);
    }

    #[Test]
    public function parseDecWithPrecisionOnly(): void
    {
        $sql = 'CREATE TABLE test (val DEC(3))';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertSame('DEC', $schema->columns['val']->type);
        self::assertSame(3, $schema->columns['val']->precision);
        self::assertSame(0, $schema->columns['val']->scale);
    }

    #[Test]
    public function parseConstraintBeforeColumn(): void
    {
        $sql = 'CREATE TABLE test (id INT, PRIMARY KEY (id), name VARCHAR(255) NOT NULL)';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertCount(2, $schema->columns);
        self::assertArrayHasKey('id', $schema->columns);
        self::assertArrayHasKey('name', $schema->columns);
        self::assertSame('VARCHAR', $schema->columns['name']->type);
        self::assertFalse($schema->columns['name']->nullable);
    }

    #[Test]
    public function parseMultipleConstraintsBetweenColumns(): void
    {
        $sql = <<<'SQL'
            CREATE TABLE test (
                id INT,
                PRIMARY KEY (id),
                UNIQUE KEY (id),
                name VARCHAR(255) NOT NULL,
                email VARCHAR(100),
                INDEX idx_name (name),
                age INT
            )
            SQL;

        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertCount(4, $schema->columns);
        self::assertArrayHasKey('name', $schema->columns);
        self::assertArrayHasKey('email', $schema->columns);
        self::assertArrayHasKey('age', $schema->columns);
        self::assertSame('INT', $schema->columns['age']->type);
    }

    #[Test]
    public function parseBacktickedTableNameStripped(): void
    {
        $sql = 'CREATE TABLE `my_table` (`id` INT PRIMARY KEY)';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertSame('my_table', $schema->tableName);
        self::assertArrayHasKey('id', $schema->columns);
    }

    #[Test]
    public function parseBacktickedColumnNamesStripped(): void
    {
        $sql = 'CREATE TABLE test (`col_a` INT, `col_b` VARCHAR(50))';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertArrayHasKey('col_a', $schema->columns);
        self::assertArrayHasKey('col_b', $schema->columns);
    }

    #[Test]
    public function parseBacktickedPrimaryKeyColumnsStripped(): void
    {
        $sql = 'CREATE TABLE test (`x` INT, `y` INT, PRIMARY KEY (`x`, `y`))';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertSame(['x', 'y'], $schema->primaryKeys);
        self::assertFalse($schema->columns['x']->nullable);
        self::assertFalse($schema->columns['y']->nullable);
    }

    #[Test]
    public function parseBitTypeIsBit(): void
    {
        $sql = 'CREATE TABLE test (flags BIT(4) NOT NULL)';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertSame('BIT', $schema->columns['flags']->type);
        self::assertSame(4, $schema->columns['flags']->length);
    }

    #[Test]
    public function parseDefaultStringWithQuotes(): void
    {
        $sql = "CREATE TABLE test (name VARCHAR(100) DEFAULT 'hello world')";
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertSame('hello world', $schema->columns['name']->default);
    }

    #[Test]
    public function parseDefaultWithNoDefaultReturnsNull(): void
    {
        $sql = 'CREATE TABLE test (id INT NOT NULL, name VARCHAR(100) NOT NULL)';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertNull($schema->columns['id']->default);
        self::assertNull($schema->columns['name']->default);
    }

    #[Test]
    public function parseEnumOrSetExtractsValues(): void
    {
        $sql = "CREATE TABLE test (status ENUM('a','b','c'), tags SET('x','y'))";
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertSame(['a', 'b', 'c'], $schema->columns['status']->enumValues);
        self::assertSame(['x', 'y'], $schema->columns['tags']->enumValues);
    }

    #[Test]
    public function parseNonEnumTypeHasNullEnumValues(): void
    {
        $sql = 'CREATE TABLE test (name VARCHAR(100))';
        $schema = (new MySqlSchemaParser())->parse($sql);

        self::assertNull($schema->columns['name']->enumValues);
    }
}
