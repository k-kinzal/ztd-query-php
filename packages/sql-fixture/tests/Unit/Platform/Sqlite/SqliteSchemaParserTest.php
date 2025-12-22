<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Sqlite;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\Sqlite\SqliteSchemaParser;
use SqlFixture\Schema\SchemaParseException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\TableSchema;

#[CoversClass(SqliteSchemaParser::class)]
#[UsesClass(ColumnDefinition::class)]
#[UsesClass(TableSchema::class)]
#[UsesClass(SchemaParseException::class)]
final class SqliteSchemaParserTest extends TestCase
{
    #[Test]
    public function parseSimpleTable(): void
    {
        $sql = 'CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)';
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertSame('users', $schema->tableName);
        self::assertCount(2, $schema->columns);
        self::assertArrayHasKey('id', $schema->columns);
        self::assertArrayHasKey('name', $schema->columns);
    }

    #[Test]
    public function parseColumnTypes(): void
    {
        $sql = <<<'SQL'
            CREATE TABLE test (
                col_int INTEGER,
                col_text TEXT,
                col_real REAL,
                col_blob BLOB
            )
            SQL;

        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertSame('INTEGER', $schema->columns['col_int']->type);
        self::assertSame('TEXT', $schema->columns['col_text']->type);
        self::assertSame('REAL', $schema->columns['col_real']->type);
        self::assertSame('BLOB', $schema->columns['col_blob']->type);
    }

    #[Test]
    public function parseVarcharWithLength(): void
    {
        $sql = 'CREATE TABLE test (name VARCHAR(100))';
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertSame('VARCHAR', $schema->columns['name']->type);
        self::assertSame(100, $schema->columns['name']->length);
    }

    #[Test]
    public function parseDecimalWithPrecisionAndScale(): void
    {
        $sql = 'CREATE TABLE test (price DECIMAL(10, 2))';
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertSame('DECIMAL', $schema->columns['price']->type);
        self::assertSame(10, $schema->columns['price']->precision);
        self::assertSame(2, $schema->columns['price']->scale);
    }

    #[Test]
    public function parseNotNullConstraint(): void
    {
        $sql = 'CREATE TABLE test (id INTEGER NOT NULL, name TEXT)';
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertFalse($schema->columns['id']->nullable);
        self::assertTrue($schema->columns['name']->nullable);
    }

    #[Test]
    public function parsePrimaryKey(): void
    {
        $sql = 'CREATE TABLE test (id INTEGER PRIMARY KEY)';
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertFalse($schema->columns['id']->nullable);
    }

    #[Test]
    public function parsePrimaryKeyAutoincrement(): void
    {
        $sql = 'CREATE TABLE test (id INTEGER PRIMARY KEY AUTOINCREMENT)';
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertTrue($schema->columns['id']->autoIncrement);
    }

    #[Test]
    public function parseTableLevelPrimaryKey(): void
    {
        $sql = 'CREATE TABLE test (a INTEGER, b TEXT, PRIMARY KEY (a, b))';
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertSame(['a', 'b'], $schema->primaryKeys);
        self::assertFalse($schema->columns['a']->nullable);
        self::assertFalse($schema->columns['b']->nullable);
    }

    #[Test]
    public function parseDefaultString(): void
    {
        $sql = "CREATE TABLE test (name TEXT DEFAULT 'default_value')";
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertSame('default_value', $schema->columns['name']->default);
    }

    #[Test]
    public function parseDefaultNull(): void
    {
        $sql = 'CREATE TABLE test (name TEXT DEFAULT NULL)';
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertNull($schema->columns['name']->default);
    }

    #[Test]
    public function parseDefaultInteger(): void
    {
        $sql = 'CREATE TABLE test (count INTEGER DEFAULT 42)';
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertSame(42, $schema->columns['count']->default);
    }

    #[Test]
    public function parseIfNotExists(): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY)';
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertSame('users', $schema->tableName);
    }

    #[Test]
    public function parseQuotedTableName(): void
    {
        $sql = 'CREATE TABLE "my_table" ("my_column" INTEGER)';
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertSame('my_table', $schema->tableName);
        self::assertArrayHasKey('my_column', $schema->columns);
    }

    #[Test]
    public function parseMultipleConstraints(): void
    {
        $sql = 'CREATE TABLE test (id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT)';
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertFalse($schema->columns['id']->nullable);
        self::assertTrue($schema->columns['id']->autoIncrement);
    }

    #[Test]
    public function parseTypeAffinity(): void
    {
        $sql = <<<'SQL'
            CREATE TABLE test (
                col_int INT,
                col_integer INTEGER,
                col_tinyint TINYINT,
                col_smallint SMALLINT,
                col_mediumint MEDIUMINT,
                col_bigint BIGINT,
                col_char CHAR(10),
                col_varchar VARCHAR(255),
                col_float FLOAT,
                col_double DOUBLE,
                col_boolean BOOLEAN,
                col_date DATE,
                col_datetime DATETIME
            )
            SQL;

        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertSame('INT', $schema->columns['col_int']->type);
        self::assertSame('INTEGER', $schema->columns['col_integer']->type);
        self::assertSame('TINYINT', $schema->columns['col_tinyint']->type);
        self::assertSame('SMALLINT', $schema->columns['col_smallint']->type);
        self::assertSame('MEDIUMINT', $schema->columns['col_mediumint']->type);
        self::assertSame('BIGINT', $schema->columns['col_bigint']->type);

        self::assertSame('CHAR', $schema->columns['col_char']->type);
        self::assertSame('VARCHAR', $schema->columns['col_varchar']->type);

        self::assertSame('FLOAT', $schema->columns['col_float']->type);
        self::assertSame('DOUBLE', $schema->columns['col_double']->type);

        self::assertSame('BOOLEAN', $schema->columns['col_boolean']->type);
        self::assertSame('DATE', $schema->columns['col_date']->type);
        self::assertSame('DATETIME', $schema->columns['col_datetime']->type);
    }

    #[Test]
    public function parseSkipsTableConstraints(): void
    {
        $sql = <<<'SQL'
            CREATE TABLE test (
                id INTEGER,
                name TEXT,
                FOREIGN KEY (id) REFERENCES other(id),
                UNIQUE (name),
                CHECK (id > 0)
            )
            SQL;

        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertCount(2, $schema->columns);
        self::assertArrayHasKey('id', $schema->columns);
        self::assertArrayHasKey('name', $schema->columns);
    }

    #[Test]
    public function parseComments(): void
    {
        $sql = <<<'SQL'
            -- This is a comment
            CREATE TABLE test (
                id INTEGER -- inline comment
            )
            SQL;

        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertSame('test', $schema->tableName);
    }

    #[Test]
    public function throwsExceptionForInvalidSql(): void
    {
        $this->expectException(SchemaParseException::class);
        (new SqliteSchemaParser())->parse('NOT A VALID SQL STATEMENT');
    }

    #[Test]
    public function throwsExceptionForEmptyTable(): void
    {
        $this->expectException(SchemaParseException::class);
        (new SqliteSchemaParser())->parse('CREATE TABLE test ()');
    }

    #[Test]
    public function parseDefaultExpression(): void
    {
        $sql = 'CREATE TABLE test (created_at TEXT DEFAULT CURRENT_TIMESTAMP)';
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertSame('CURRENT_TIMESTAMP', $schema->columns['created_at']->default);
    }

    #[Test]
    public function parseGeneratedColumn(): void
    {
        $sql = 'CREATE TABLE test (a INTEGER, b INTEGER, c INTEGER AS (a + b))';
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertTrue($schema->columns['c']->generated);
        self::assertFalse($schema->columns['a']->generated);
    }

    #[Test]
    public function parseDefaultBooleanTrue(): void
    {
        $sql = 'CREATE TABLE test (active INTEGER DEFAULT TRUE)';
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertTrue($schema->columns['active']->default);
    }

    #[Test]
    public function parseDefaultBooleanFalse(): void
    {
        $sql = 'CREATE TABLE test (active INTEGER DEFAULT FALSE)';
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertFalse($schema->columns['active']->default);
    }

    #[Test]
    public function parseDefaultNumericOne(): void
    {
        $sql = 'CREATE TABLE test (flag INTEGER DEFAULT 1)';
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertTrue($schema->columns['flag']->default);
    }

    #[Test]
    public function parseDefaultNumericZero(): void
    {
        $sql = 'CREATE TABLE test (flag INTEGER DEFAULT 0)';
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertFalse($schema->columns['flag']->default);
    }

    #[Test]
    public function parseDefaultFloat(): void
    {
        $sql = 'CREATE TABLE test (price REAL DEFAULT 9.99)';
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertSame(9.99, $schema->columns['price']->default);
    }

    #[Test]
    public function parseDefaultCurrentDate(): void
    {
        $sql = 'CREATE TABLE test (d TEXT DEFAULT CURRENT_DATE)';
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertSame('CURRENT_DATE', $schema->columns['d']->default);
    }

    #[Test]
    public function parseDefaultCurrentTime(): void
    {
        $sql = 'CREATE TABLE test (t TEXT DEFAULT CURRENT_TIME)';
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertSame('CURRENT_TIME', $schema->columns['t']->default);
    }

    #[Test]
    public function parseDefaultExpression2(): void
    {
        $sql = 'CREATE TABLE test (val INTEGER DEFAULT (1+2))';
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertSame('(1+2)', $schema->columns['val']->default);
    }

    #[Test]
    public function parseIntegerPrimaryKeyNotAutoincrement(): void
    {
        $sql = 'CREATE TABLE test (id INTEGER PRIMARY KEY, name TEXT)';
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertFalse($schema->columns['id']->autoIncrement);
        self::assertFalse($schema->columns['id']->nullable);
    }

    #[Test]
    public function parseIntegerPrimaryKeyAutoincrement(): void
    {
        $sql = 'CREATE TABLE test (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)';
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertTrue($schema->columns['id']->autoIncrement);
    }

    #[Test]
    public function parseNonIntegerPrimaryKeyAutoincrement(): void
    {
        $sql = 'CREATE TABLE test (code TEXT PRIMARY KEY, name TEXT)';
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertFalse($schema->columns['code']->autoIncrement);
        self::assertFalse($schema->columns['code']->nullable);
    }

    #[Test]
    public function parseColumnWithNoType(): void
    {
        $sql = 'CREATE TABLE test (val)';
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertSame('BLOB', $schema->columns['val']->type);
    }

    #[Test]
    public function parseBlockComment(): void
    {
        $sql = "/* comment */ CREATE TABLE test (id INTEGER)";
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertSame('test', $schema->tableName);
    }

    #[Test]
    public function parseNonGeneratedColumn(): void
    {
        $sql = 'CREATE TABLE test (id INTEGER, name TEXT)';
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertFalse($schema->columns['id']->generated);
        self::assertFalse($schema->columns['name']->generated);
    }

    #[Test]
    public function parseUnsignedAlwaysFalse(): void
    {
        $sql = 'CREATE TABLE test (id INTEGER)';
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertFalse($schema->columns['id']->unsigned);
    }

    #[Test]
    public function parseEnumValuesAlwaysNull(): void
    {
        $sql = 'CREATE TABLE test (id INTEGER)';
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertNull($schema->columns['id']->enumValues);
    }

    #[Test]
    public function parseConstraintKeyword(): void
    {
        $sql = 'CREATE TABLE test (id INTEGER, CONSTRAINT pk PRIMARY KEY (id))';
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertCount(1, $schema->columns);
        self::assertArrayHasKey('id', $schema->columns);
    }

    #[Test]
    public function parseDefaultWithNotNull(): void
    {
        $sql = "CREATE TABLE test (name TEXT NOT NULL DEFAULT 'test_val')";
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertSame('test_val', $schema->columns['name']->default);
        self::assertFalse($schema->columns['name']->nullable);
    }

    #[Test]
    public function parseSchemaQualifiedTableName(): void
    {
        $sql = 'CREATE TABLE main.users (id INTEGER PRIMARY KEY)';
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertSame('users', $schema->tableName);
    }

    #[Test]
    public function parseNoParentheses(): void
    {
        $this->expectException(SchemaParseException::class);
        (new SqliteSchemaParser())->parse('CREATE TABLE test');
    }

    #[Test]
    public function parseIsTableConstraintCaseInsensitive(): void
    {
        $sql = 'CREATE TABLE test (id INTEGER, foreign key (id) REFERENCES other(id))';
        $schema = (new SqliteSchemaParser())->parse($sql);
        self::assertCount(1, $schema->columns);
    }

    #[Test]
    public function parseBacktickedColumn(): void
    {
        $sql = 'CREATE TABLE test (`col` INTEGER NOT NULL)';
        $schema = (new SqliteSchemaParser())->parse($sql);
        self::assertArrayHasKey('col', $schema->columns);
    }

    #[Test]
    public function parseQuotedPrimaryKeyColumns(): void
    {
        $sql = 'CREATE TABLE test ("a" INTEGER, "b" TEXT, PRIMARY KEY ("a", "b"))';
        $schema = (new SqliteSchemaParser())->parse($sql);
        self::assertSame(['a', 'b'], $schema->primaryKeys);
    }

    #[Test]
    public function parseDefaultStringWithNotNull(): void
    {
        $sql = "CREATE TABLE test (col TEXT DEFAULT 'hello' NOT NULL)";
        $schema = (new SqliteSchemaParser())->parse($sql);
        self::assertSame('hello', $schema->columns['col']->default);
        self::assertFalse($schema->columns['col']->nullable);
    }

    #[Test]
    public function parseDefaultWithCollate(): void
    {
        $sql = "CREATE TABLE test (col TEXT DEFAULT 'val' COLLATE NOCASE)";
        $schema = (new SqliteSchemaParser())->parse($sql);
        self::assertSame('val', $schema->columns['col']->default);
    }

    #[Test]
    public function parseMultilineWithWhitespace(): void
    {
        $sql = "  CREATE   TABLE   test  (  id   INTEGER  NOT  NULL  ) ";
        $schema = (new SqliteSchemaParser())->parse($sql);
        self::assertSame('test', $schema->tableName);
        self::assertFalse($schema->columns['id']->nullable);
    }

    #[Test]
    public function parseLengthOnlyColumn(): void
    {
        $sql = 'CREATE TABLE test (col VARCHAR(50) NOT NULL)';
        $schema = (new SqliteSchemaParser())->parse($sql);
        self::assertSame(50, $schema->columns['col']->length);
        self::assertNull($schema->columns['col']->precision);
    }

    #[Test]
    public function parseIntegerPrimaryKeyWithoutAutoincrementIsNotAutoIncrement(): void
    {
        $sql = 'CREATE TABLE test (id INTEGER PRIMARY KEY)';
        $schema = (new SqliteSchemaParser())->parse($sql);
        self::assertFalse($schema->columns['id']->autoIncrement);
    }

    #[Test]
    public function parseTextPrimaryKeyNoAutoIncrement(): void
    {
        $sql = 'CREATE TABLE test (code TEXT PRIMARY KEY, name TEXT)';
        $schema = (new SqliteSchemaParser())->parse($sql);
        self::assertFalse($schema->columns['code']->autoIncrement);
        self::assertFalse($schema->columns['code']->nullable);
    }

    #[Test]
    public function parseDefaultInteger42(): void
    {
        $sql = 'CREATE TABLE test (count INTEGER DEFAULT 42)';
        $schema = (new SqliteSchemaParser())->parse($sql);
        self::assertSame(42, $schema->columns['count']->default);
    }

    #[Test]
    public function parseLowercaseCreateTable(): void
    {
        $sql = 'create table users (id integer primary key, name text not null)';
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertSame('users', $schema->tableName);
        self::assertSame('INTEGER', $schema->columns['id']->type);
        self::assertSame('TEXT', $schema->columns['name']->type);
        self::assertFalse($schema->columns['id']->nullable);
        self::assertFalse($schema->columns['name']->nullable);
    }

    #[Test]
    public function parseLowercaseIfNotExists(): void
    {
        $sql = 'create table if not exists users (id integer primary key)';
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertSame('users', $schema->tableName);
    }

    #[Test]
    public function parseLowercaseAutoincrement(): void
    {
        $sql = 'create table test (id integer primary key autoincrement)';
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertTrue($schema->columns['id']->autoIncrement);
    }

    #[Test]
    public function parseLowercaseConstraints(): void
    {
        $sql = <<<'SQL'
            create table test (
                id integer,
                name text,
                foreign key (id) references other(id),
                unique (name),
                check (id > 0),
                constraint my_constraint unique (id, name)
            )
            SQL;

        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertCount(2, $schema->columns);
    }

    #[Test]
    public function parseLowercaseTablePrimaryKey(): void
    {
        $sql = 'create table test (a integer, b text, primary key (a, b))';
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertSame(['a', 'b'], $schema->primaryKeys);
        self::assertFalse($schema->columns['a']->nullable);
    }

    #[Test]
    public function parseLowercaseDecimalType(): void
    {
        $sql = 'create table test (val decimal(10, 2))';
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertSame('DECIMAL', $schema->columns['val']->type);
        self::assertSame(10, $schema->columns['val']->precision);
        self::assertSame(2, $schema->columns['val']->scale);
    }

    #[Test]
    public function parseLowercaseVarcharWithLength(): void
    {
        $sql = 'create table test (name varchar(100))';
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertSame('VARCHAR', $schema->columns['name']->type);
        self::assertSame(100, $schema->columns['name']->length);
    }

    #[Test]
    public function parseLowercaseGeneratedColumn(): void
    {
        $sql = 'create table test (a integer, b integer, c integer as (a + b))';
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertTrue($schema->columns['c']->generated);
    }

    #[Test]
    public function parseLowercaseDefaultValues(): void
    {
        $sql = <<<'SQL'
            create table test (
                col_null text default null,
                col_true integer default true,
                col_false integer default false,
                col_str text default 'hello',
                col_int integer default 42,
                col_float real default 9.99,
                col_ts text default current_timestamp,
                col_date text default current_date,
                col_time text default current_time,
                col_expr integer default (1+2)
            )
            SQL;

        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertNull($schema->columns['col_null']->default);
        self::assertTrue($schema->columns['col_true']->default);
        self::assertFalse($schema->columns['col_false']->default);
        self::assertSame('hello', $schema->columns['col_str']->default);
        self::assertSame(42, $schema->columns['col_int']->default);
        self::assertSame(9.99, $schema->columns['col_float']->default);
        self::assertSame('current_timestamp', $schema->columns['col_ts']->default);
        self::assertSame('current_date', $schema->columns['col_date']->default);
        self::assertSame('current_time', $schema->columns['col_time']->default);
        self::assertSame('(1+2)', $schema->columns['col_expr']->default);
    }

    #[Test]
    public function parseLowercaseDefaultWithTrailingConstraints(): void
    {
        $sql = "create table test (name text default 'test' not null collate nocase)";
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertSame('test', $schema->columns['name']->default);
        self::assertFalse($schema->columns['name']->nullable);
    }

    #[Test]
    public function parseLowercaseTypeAffinity(): void
    {
        $sql = <<<'SQL'
            create table test (
                col_int int,
                col_tinyint tinyint,
                col_smallint smallint,
                col_bigint bigint,
                col_float float,
                col_double double,
                col_boolean boolean,
                col_date date,
                col_datetime datetime,
                col_blob blob
            )
            SQL;

        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertSame('INT', $schema->columns['col_int']->type);
        self::assertSame('TINYINT', $schema->columns['col_tinyint']->type);
        self::assertSame('SMALLINT', $schema->columns['col_smallint']->type);
        self::assertSame('BIGINT', $schema->columns['col_bigint']->type);
        self::assertSame('FLOAT', $schema->columns['col_float']->type);
        self::assertSame('DOUBLE', $schema->columns['col_double']->type);
        self::assertSame('BOOLEAN', $schema->columns['col_boolean']->type);
        self::assertSame('DATE', $schema->columns['col_date']->type);
        self::assertSame('DATETIME', $schema->columns['col_datetime']->type);
        self::assertSame('BLOB', $schema->columns['col_blob']->type);
    }

    #[Test]
    public function parseWithLeadingTrailingWhitespace(): void
    {
        $sql = "  \n  create table test ( \n  id integer , \n  name text \n ) \n ";
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertSame('test', $schema->tableName);
        self::assertCount(2, $schema->columns);
    }

    #[Test]
    public function parseMixedCaseKeywords(): void
    {
        $sql = 'Create Table test (id Integer Primary Key, name Varchar(100) Not Null)';
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertSame('test', $schema->tableName);
        self::assertSame('INTEGER', $schema->columns['id']->type);
        self::assertSame('VARCHAR', $schema->columns['name']->type);
        self::assertFalse($schema->columns['id']->nullable);
        self::assertFalse($schema->columns['name']->nullable);
    }

    #[Test]
    public function parseLowercaseSchemaQualified(): void
    {
        $sql = 'create table main.users (id integer primary key)';
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertSame('users', $schema->tableName);
    }

    #[Test]
    public function parseDefaultStringWithCheckConstraint(): void
    {
        $sql = "CREATE TABLE test (val TEXT DEFAULT 'hello' CHECK (val <> ''))";
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertSame('hello', $schema->columns['val']->default);
    }

    #[Test]
    public function parseConstraintBeforeColumn(): void
    {
        $sql = <<<'SQL'
            CREATE TABLE test (
                id INTEGER,
                PRIMARY KEY (id),
                name TEXT NOT NULL
            )
            SQL;

        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertCount(2, $schema->columns);
        self::assertArrayHasKey('id', $schema->columns);
        self::assertArrayHasKey('name', $schema->columns);
        self::assertSame('TEXT', $schema->columns['name']->type);
        self::assertFalse($schema->columns['name']->nullable);
    }

    #[Test]
    public function parseMultipleConstraintsBetweenColumns(): void
    {
        $sql = <<<'SQL'
            CREATE TABLE test (
                id INTEGER,
                CONSTRAINT pk PRIMARY KEY (id),
                UNIQUE (id),
                name TEXT NOT NULL,
                email TEXT,
                CHECK (name <> ''),
                age INTEGER
            )
            SQL;

        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertCount(4, $schema->columns);
        self::assertArrayHasKey('name', $schema->columns);
        self::assertArrayHasKey('email', $schema->columns);
        self::assertArrayHasKey('age', $schema->columns);
        self::assertSame('INTEGER', $schema->columns['age']->type);
    }

    #[Test]
    public function parseNonIntegerPrimaryKeyIsNotAutoIncrement(): void
    {
        $sql = 'CREATE TABLE test (code TEXT PRIMARY KEY, name TEXT)';
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertFalse($schema->columns['code']->autoIncrement);
        self::assertSame('TEXT', $schema->columns['code']->type);
        self::assertFalse($schema->columns['code']->nullable);
    }

    #[Test]
    public function parseDefaultExpressionWithOnlyOpeningParen(): void
    {
        $sql = "CREATE TABLE test (val TEXT DEFAULT 'value(test')";
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertSame('value(test', $schema->columns['val']->default);
    }

    #[Test]
    public function parseColumnWithNoTypeReturnsBlobViaExtractType(): void
    {
        $sql = 'CREATE TABLE test (col1, col2 INTEGER)';
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertSame('BLOB', $schema->columns['col1']->type);
        self::assertSame('INTEGER', $schema->columns['col2']->type);
    }

    #[Test]
    public function parseDefaultExpressionWrappedInParens(): void
    {
        $sql = 'CREATE TABLE test (val INTEGER DEFAULT (10 * 2))';
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertSame('(10 * 2)', $schema->columns['val']->default);
    }

    #[Test]
    public function parseDefaultExpressionOnlyClosingParen(): void
    {
        $sql = "CREATE TABLE test (val TEXT DEFAULT 'test)')";
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertSame('test)', $schema->columns['val']->default);
    }

    #[Test]
    public function parseDefaultCurrentTimestampUpperCase(): void
    {
        $sql = 'CREATE TABLE test (ts TEXT DEFAULT CURRENT_TIMESTAMP)';
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertSame('CURRENT_TIMESTAMP', $schema->columns['ts']->default);
    }

    #[Test]
    public function parseDefaultCurrentTimestampNotPartOfLargerWord(): void
    {
        $sql = "CREATE TABLE test (ts TEXT DEFAULT 'NOT_CURRENT_TIMESTAMP_EXTRA')";
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertSame('NOT_CURRENT_TIMESTAMP_EXTRA', $schema->columns['ts']->default);
    }

    #[Test]
    public function parseTrimsDefinitionsInSplitColumnDefinitions(): void
    {
        $sql = "CREATE TABLE test (  id INTEGER  ,  name TEXT  )";
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertCount(2, $schema->columns);
        self::assertArrayHasKey('id', $schema->columns);
        self::assertArrayHasKey('name', $schema->columns);
    }

    #[Test]
    public function parseColumnDefinitionTrimsRest(): void
    {
        $sql = "CREATE TABLE test (id   INTEGER   NOT NULL)";
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertSame('INTEGER', $schema->columns['id']->type);
        self::assertFalse($schema->columns['id']->nullable);
    }

    #[Test]
    public function parseDefaultTrimsValue(): void
    {
        $sql = "CREATE TABLE test (val TEXT DEFAULT 'trimmed' NOT NULL)";
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertSame('trimmed', $schema->columns['val']->default);
        self::assertFalse($schema->columns['val']->nullable);
    }

    #[Test]
    public function parseIsTableConstraintWithLeadingWhitespace(): void
    {
        $sql = "CREATE TABLE test (id INTEGER, \n  PRIMARY KEY (id))";
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertCount(1, $schema->columns);
    }

    #[Test]
    public function parseColumnParensEqualStartReturnsNull(): void
    {
        $this->expectException(SchemaParseException::class);
        (new SqliteSchemaParser())->parse('CREATE TABLE test )( ');
    }

    #[Test]
    public function parseIntegerPrimaryKeyWithTextPrimaryKeyElseBranch(): void
    {
        $sql = <<<'SQL'
            CREATE TABLE test (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                code TEXT PRIMARY KEY,
                name VARCHAR(100)
            )
            SQL;

        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertTrue($schema->columns['id']->autoIncrement);
        self::assertFalse($schema->columns['code']->autoIncrement);
    }

    #[Test]
    public function parseDefaultStringWithDoubleQuotes(): void
    {
        $sql = 'CREATE TABLE test (name TEXT DEFAULT "hello_world")';
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertSame('hello_world', $schema->columns['name']->default);
    }

    #[Test]
    public function parseDefaultReferencesConstraint(): void
    {
        $sql = "CREATE TABLE test (user_id INTEGER DEFAULT 1 REFERENCES users(id))";
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertTrue($schema->columns['user_id']->default);
    }

    #[Test]
    public function parseDefaultWithGeneratedKeyword(): void
    {
        $sql = "CREATE TABLE test (val INTEGER DEFAULT 5, gen INTEGER AS (val * 2))";
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertSame(5, $schema->columns['val']->default);
        self::assertTrue($schema->columns['gen']->generated);
    }

    #[Test]
    public function parseNormalizeSqlRemovesBlockComments(): void
    {
        $sql = "CREATE TABLE /* block comment */ test (id /* another */ INTEGER)";
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertSame('test', $schema->tableName);
        self::assertSame('INTEGER', $schema->columns['id']->type);
    }

    #[Test]
    public function parseNormalizeSqlTrimsWhitespace(): void
    {
        $sql = "\n\n   CREATE TABLE test (id INTEGER)   \n\n";
        $schema = (new SqliteSchemaParser())->parse($sql);

        self::assertSame('test', $schema->tableName);
    }
}
