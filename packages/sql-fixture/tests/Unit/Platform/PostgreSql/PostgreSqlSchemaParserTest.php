<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\PostgreSql\PostgreSqlSchemaParser;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\SchemaParseException;
use SqlFixture\Schema\TableSchema;

#[CoversClass(PostgreSqlSchemaParser::class)]
#[UsesClass(ColumnDefinition::class)]
#[UsesClass(TableSchema::class)]
#[UsesClass(SchemaParseException::class)]
#[CoversClass(\SqlFixture\Platform\PostgreSql\Schema\ColumnParser::class)]
#[CoversClass(\SqlFixture\Platform\PostgreSql\Schema\DefaultExpression::class)]
#[CoversClass(\SqlFixture\Platform\PostgreSql\Schema\DefinitionList::class)]
#[CoversClass(\SqlFixture\Platform\PostgreSql\Schema\TableSyntax::class)]
#[CoversClass(\SqlFixture\Platform\PostgreSql\Schema\TypeDeclaration::class)]
#[CoversClass(\SqlFixture\Schema\DefinitionSegments::class)]
#[UsesClass(\SqlFixture\Schema\SchemaParserInterface::class)]
#[UsesClass(\SqlFixture\Schema\TypeShape::class)]
final class PostgreSqlSchemaParserTest extends TestCase
{
    #[Test]
    public function testParseSimpleTable(): void
    {
        $sql = 'CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('users', $schema->tableName);
        self::assertCount(2, $schema->columns);
        self::assertArrayHasKey('id', $schema->columns);
        self::assertArrayHasKey('name', $schema->columns);
    }

    #[Test]
    public function testParseColumnTypes(): void
    {
        $sql = <<<'SQL'
            CREATE TABLE test (
                col_int INTEGER,
                col_bigint BIGINT,
                col_smallint SMALLINT,
                col_text TEXT,
                col_boolean BOOLEAN,
                col_real REAL,
                col_double DOUBLE PRECISION,
                col_date DATE,
                col_timestamp TIMESTAMP,
                col_timestamptz TIMESTAMPTZ,
                col_uuid UUID,
                col_jsonb JSONB,
                col_bytea BYTEA,
                col_inet INET
            )
            SQL;

        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('INTEGER', $schema->columns['col_int']->type);
        self::assertSame('BIGINT', $schema->columns['col_bigint']->type);
        self::assertSame('SMALLINT', $schema->columns['col_smallint']->type);
        self::assertSame('TEXT', $schema->columns['col_text']->type);
        self::assertSame('BOOLEAN', $schema->columns['col_boolean']->type);
        self::assertSame('REAL', $schema->columns['col_real']->type);
        self::assertSame('DOUBLE PRECISION', $schema->columns['col_double']->type);
        self::assertSame('DATE', $schema->columns['col_date']->type);
        self::assertSame('TIMESTAMP', $schema->columns['col_timestamp']->type);
        self::assertSame('TIMESTAMPTZ', $schema->columns['col_timestamptz']->type);
        self::assertSame('UUID', $schema->columns['col_uuid']->type);
        self::assertSame('JSONB', $schema->columns['col_jsonb']->type);
        self::assertSame('BYTEA', $schema->columns['col_bytea']->type);
        self::assertSame('INET', $schema->columns['col_inet']->type);
    }

    #[Test]
    public function testParseSerialType(): void
    {
        $sql = 'CREATE TABLE test (id SERIAL PRIMARY KEY, name TEXT)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('INTEGER', $schema->columns['id']->type);
        self::assertTrue($schema->columns['id']->autoIncrement);
    }

    #[Test]
    public function testParseBigSerialType(): void
    {
        $sql = 'CREATE TABLE test (id BIGSERIAL PRIMARY KEY)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('BIGINT', $schema->columns['id']->type);
        self::assertTrue($schema->columns['id']->autoIncrement);
    }

    #[Test]
    public function testParseSmallSerialType(): void
    {
        $sql = 'CREATE TABLE test (id SMALLSERIAL PRIMARY KEY)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('SMALLINT', $schema->columns['id']->type);
        self::assertTrue($schema->columns['id']->autoIncrement);
    }

    #[Test]
    public function testParseVarcharWithLength(): void
    {
        $sql = 'CREATE TABLE test (name VARCHAR(100))';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('VARCHAR', $schema->columns['name']->type);
        self::assertSame(100, $schema->columns['name']->length);
    }

    #[Test]
    public function testParseNumericWithPrecisionAndScale(): void
    {
        $sql = 'CREATE TABLE test (price NUMERIC(10, 2))';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('NUMERIC', $schema->columns['price']->type);
        self::assertSame(10, $schema->columns['price']->precision);
        self::assertSame(2, $schema->columns['price']->scale);
    }

    #[Test]
    public function testParseNotNullConstraint(): void
    {
        $sql = 'CREATE TABLE test (id INTEGER NOT NULL, name TEXT)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertFalse($schema->columns['id']->nullable);
        self::assertTrue($schema->columns['name']->nullable);
    }

    #[Test]
    public function testParsePrimaryKey(): void
    {
        $sql = 'CREATE TABLE test (id INTEGER PRIMARY KEY)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertFalse($schema->columns['id']->nullable);
    }

    #[Test]
    public function testParseTableLevelPrimaryKey(): void
    {
        $sql = 'CREATE TABLE test (a INTEGER, b TEXT, PRIMARY KEY (a, b))';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame(['a', 'b'], $schema->primaryKeys);
        self::assertFalse($schema->columns['a']->nullable);
        self::assertFalse($schema->columns['b']->nullable);
    }

    #[Test]
    public function testParseDefaultString(): void
    {
        $sql = "CREATE TABLE test (name TEXT DEFAULT 'default_value')";
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('default_value', $schema->columns['name']->default);
    }

    #[Test]
    public function testParseDefaultNull(): void
    {
        $sql = 'CREATE TABLE test (name TEXT DEFAULT NULL)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertNull($schema->columns['name']->default);
    }

    #[Test]
    public function testParseDefaultInteger(): void
    {
        $sql = 'CREATE TABLE test (count INTEGER DEFAULT 42)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame(42, $schema->columns['count']->default);
    }

    #[Test]
    public function testParseDefaultBoolean(): void
    {
        $sql = 'CREATE TABLE test (active BOOLEAN DEFAULT TRUE)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertTrue($schema->columns['active']->default);
    }

    #[Test]
    public function testParseDefaultFunctionCall(): void
    {
        $sql = 'CREATE TABLE test (id UUID DEFAULT gen_random_uuid())';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('gen_random_uuid()', $schema->columns['id']->default);
    }

    #[Test]
    public function testParseDefaultTypeCast(): void
    {
        $sql = "CREATE TABLE test (data JSONB DEFAULT '{}'::jsonb)";
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame("'{}'::jsonb", $schema->columns['data']->default);
    }

    #[Test]
    public function testParseIfNotExists(): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('users', $schema->tableName);
    }

    #[Test]
    public function testParseQuotedTableName(): void
    {
        $sql = 'CREATE TABLE "my_table" ("my_column" INTEGER)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('my_table', $schema->tableName);
        self::assertArrayHasKey('my_column', $schema->columns);
    }

    #[Test]
    public function testParseSchemaQualifiedTableName(): void
    {
        $sql = 'CREATE TABLE public.users (id INTEGER PRIMARY KEY)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('users', $schema->tableName);
    }

    #[Test]
    public function testParseSkipsTableConstraints(): void
    {
        $sql = <<<'SQL'
            CREATE TABLE test (
                id INTEGER,
                name TEXT,
                FOREIGN KEY (id) REFERENCES other(id),
                UNIQUE (name),
                CHECK (id > 0),
                CONSTRAINT my_constraint UNIQUE (id, name)
            )
            SQL;

        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertCount(2, $schema->columns);
        self::assertArrayHasKey('id', $schema->columns);
        self::assertArrayHasKey('name', $schema->columns);
    }

    #[Test]
    public function testParseComments(): void
    {
        $sql = <<<'SQL'
            -- This is a comment
            CREATE TABLE test (
                id INTEGER -- inline comment
            )
            SQL;

        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('test', $schema->tableName);
    }

    #[Test]
    public function testThrowsExceptionForInvalidSql(): void
    {
        $this->expectException(SchemaParseException::class);
        (new PostgreSqlSchemaParser())->parse('NOT A VALID SQL STATEMENT');
    }

    #[Test]
    public function testThrowsExceptionForEmptyTable(): void
    {
        $this->expectException(SchemaParseException::class);
        (new PostgreSqlSchemaParser())->parse('CREATE TABLE test ()');
    }

    #[Test]
    public function testParseUnsignedAlwaysFalse(): void
    {
        $sql = 'CREATE TABLE test (id INTEGER, price NUMERIC(10, 2))';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertFalse($schema->columns['id']->unsigned);
        self::assertFalse($schema->columns['price']->unsigned);
    }

    #[Test]
    public function testParseGeneratedColumn(): void
    {
        $sql = 'CREATE TABLE test (a INTEGER, b INTEGER, c INTEGER GENERATED ALWAYS AS (a + b) STORED)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertTrue($schema->columns['c']->generated);
    }

    #[Test]
    public function testParseArrayType(): void
    {
        $sql = 'CREATE TABLE test (tags TEXT[])';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('TEXT_ARRAY', $schema->columns['tags']->type);
    }

    #[Test]
    public function testParseIntegerArrayType(): void
    {
        $sql = 'CREATE TABLE test (ids INTEGER[])';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('INTEGER_ARRAY', $schema->columns['ids']->type);
    }

    #[Test]
    public function testParseTimestampWithTimeZone(): void
    {
        $sql = 'CREATE TABLE test (created_at TIMESTAMP WITH TIME ZONE)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('TIMESTAMP WITH TIME ZONE', $schema->columns['created_at']->type);
    }

    #[Test]
    public function testParseTimestampWithoutTimeZone(): void
    {
        $sql = 'CREATE TABLE test (created_at TIMESTAMP WITHOUT TIME ZONE)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('TIMESTAMP WITHOUT TIME ZONE', $schema->columns['created_at']->type);
    }

    #[Test]
    public function testParseTimeWithTimeZone(): void
    {
        $sql = 'CREATE TABLE test (t TIME WITH TIME ZONE)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('TIME WITH TIME ZONE', $schema->columns['t']->type);
    }

    #[Test]
    public function testParseDoublePrecision(): void
    {
        $sql = 'CREATE TABLE test (val DOUBLE PRECISION)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('DOUBLE PRECISION', $schema->columns['val']->type);
    }

    #[Test]
    public function testParseCharacterVarying(): void
    {
        $sql = 'CREATE TABLE test (name CHARACTER VARYING(100))';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('CHARACTER VARYING', $schema->columns['name']->type);
        self::assertSame(100, $schema->columns['name']->length);
    }

    #[Test]
    public function testParseNumericWithPrecisionOnly(): void
    {
        $sql = 'CREATE TABLE test (val NUMERIC(8))';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('NUMERIC', $schema->columns['val']->type);
        self::assertSame(8, $schema->columns['val']->precision);
        self::assertSame(0, $schema->columns['val']->scale);
    }

    #[Test]
    public function testParseUuidType(): void
    {
        $sql = 'CREATE TABLE test (id UUID NOT NULL)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('UUID', $schema->columns['id']->type);
        self::assertFalse($schema->columns['id']->nullable);
    }

    #[Test]
    public function testParseJsonbType(): void
    {
        $sql = 'CREATE TABLE test (data JSONB)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('JSONB', $schema->columns['data']->type);
    }

    #[Test]
    public function testParseByteaType(): void
    {
        $sql = 'CREATE TABLE test (content BYTEA)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('BYTEA', $schema->columns['content']->type);
    }

    #[Test]
    public function testParseInetType(): void
    {
        $sql = 'CREATE TABLE test (ip INET)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('INET', $schema->columns['ip']->type);
    }

    #[Test]
    public function testParseCidrType(): void
    {
        $sql = 'CREATE TABLE test (network CIDR)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('CIDR', $schema->columns['network']->type);
    }

    #[Test]
    public function testParseMacaddrType(): void
    {
        $sql = 'CREATE TABLE test (mac MACADDR)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('MACADDR', $schema->columns['mac']->type);
    }

    #[Test]
    public function testParseMoneyType(): void
    {
        $sql = 'CREATE TABLE test (price MONEY)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('MONEY', $schema->columns['price']->type);
    }

    #[Test]
    public function testParseIntervalType(): void
    {
        $sql = 'CREATE TABLE test (duration INTERVAL)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('INTERVAL', $schema->columns['duration']->type);
    }

    #[Test]
    public function testParseXmlType(): void
    {
        $sql = 'CREATE TABLE test (data XML)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('XML', $schema->columns['data']->type);
    }

    #[Test]
    public function testParseExcludeConstraintSkipped(): void
    {
        $sql = <<<'SQL'
            CREATE TABLE test (
                id INTEGER,
                range_col INTEGER,
                EXCLUDE USING gist (range_col WITH &&)
            )
            SQL;

        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertCount(2, $schema->columns);
        self::assertArrayHasKey('id', $schema->columns);
        self::assertArrayHasKey('range_col', $schema->columns);
    }

    #[Test]
    public function testParseDefaultCurrentTimestamp(): void
    {
        $sql = 'CREATE TABLE test (created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('CURRENT_TIMESTAMP', $schema->columns['created_at']->default);
    }

    #[Test]
    public function testParseMultipleSerialColumns(): void
    {
        $sql = <<<'SQL'
            CREATE TABLE test (
                id SERIAL PRIMARY KEY,
                seq BIGSERIAL NOT NULL,
                name TEXT
            )
            SQL;

        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('INTEGER', $schema->columns['id']->type);
        self::assertTrue($schema->columns['id']->autoIncrement);
        self::assertSame('BIGINT', $schema->columns['seq']->type);
        self::assertTrue($schema->columns['seq']->autoIncrement);
        self::assertSame('TEXT', $schema->columns['name']->type);
        self::assertFalse($schema->columns['name']->autoIncrement);
    }

    #[Test]
    public function testParseDefaultFalseValue(): void
    {
        $sql = 'CREATE TABLE test (active BOOLEAN DEFAULT FALSE)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertFalse($schema->columns['active']->default);
    }

    #[Test]
    public function testParseDefaultFloatValue(): void
    {
        $sql = 'CREATE TABLE test (price NUMERIC(10,2) DEFAULT 9.99)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame(9.99, $schema->columns['price']->default);
    }

    #[Test]
    public function testParseDefaultIntegerValue(): void
    {
        $sql = 'CREATE TABLE test (count INTEGER DEFAULT 0)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame(0, $schema->columns['count']->default);
    }

    #[Test]
    public function testParseDefaultNow(): void
    {
        $sql = 'CREATE TABLE test (created_at TIMESTAMP DEFAULT NOW())';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('NOW()', $schema->columns['created_at']->default);
    }

    #[Test]
    public function testParseNonGeneratedColumn(): void
    {
        $sql = 'CREATE TABLE test (id INTEGER, name TEXT)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertFalse($schema->columns['id']->generated);
        self::assertFalse($schema->columns['name']->generated);
    }

    #[Test]
    public function testParseNonAutoIncrementColumn(): void
    {
        $sql = 'CREATE TABLE test (id INTEGER, name TEXT)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertFalse($schema->columns['id']->autoIncrement);
    }

    #[Test]
    public function testParseEnumValuesAlwaysNull(): void
    {
        $sql = 'CREATE TABLE test (id INTEGER)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertNull($schema->columns['id']->enumValues);
    }

    #[Test]
    public function testParseColumnWithEmptyType(): void
    {
        $sql = 'CREATE TABLE test (id)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('TEXT', $schema->columns['id']->type);
    }

    #[Test]
    public function testParseBlockComment(): void
    {
        $sql = '/* comment */ CREATE TABLE test (id INTEGER)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('test', $schema->tableName);
    }

    #[Test]
    public function testParseNoParentheses(): void
    {
        $this->expectException(SchemaParseException::class);
        (new PostgreSqlSchemaParser())->parse('CREATE TABLE test');
    }

    #[Test]
    public function testParseDefaultExpressionParenthesized(): void
    {
        $sql = 'CREATE TABLE test (val INTEGER DEFAULT (1+2))';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('(1+2)', $schema->columns['val']->default);
    }

    #[Test]
    public function testParseDecimalWithPrecisionOnly(): void
    {
        $sql = 'CREATE TABLE test (val DECIMAL(8))';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('DECIMAL', $schema->columns['val']->type);
        self::assertSame(8, $schema->columns['val']->precision);
        self::assertSame(0, $schema->columns['val']->scale);
    }

    #[Test]
    public function testParseTimeWithoutTimeZone(): void
    {
        $sql = 'CREATE TABLE test (t TIME WITHOUT TIME ZONE)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('TIME WITHOUT TIME ZONE', $schema->columns['t']->type);
    }

    #[Test]
    public function testParseDefaultNullValueExplicit(): void
    {
        $sql = 'CREATE TABLE test (val VARCHAR(100) DEFAULT NULL)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertNull($schema->columns['val']->default);
    }

    #[Test]
    public function testParseQuotedPrimaryKeyColumns(): void
    {
        $sql = 'CREATE TABLE test ("a" INTEGER, "b" TEXT, PRIMARY KEY ("a", "b"))';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame(['a', 'b'], $schema->primaryKeys);
    }

    #[Test]
    public function testParseMultilineWithWhitespace(): void
    {
        $sql = '  CREATE   TABLE   test  (  id   INTEGER  NOT  NULL  ) ';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);
        self::assertSame('test', $schema->tableName);
        self::assertFalse($schema->columns['id']->nullable);
    }

    #[Test]
    public function testParseDecPrecisionOnly(): void
    {
        $sql = 'CREATE TABLE test (val DEC(6))';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);
        self::assertSame(6, $schema->columns['val']->precision);
        self::assertSame(0, $schema->columns['val']->scale);
    }

    #[Test]
    public function testParseDefaultStringDoubleQuotes(): void
    {
        $sql = 'CREATE TABLE test (name TEXT DEFAULT "hello")';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);
        self::assertSame('hello', $schema->columns['name']->default);
    }

    #[Test]
    public function testParseColumnWithNoTypeGetsTextDefault(): void
    {
        $sql = 'CREATE TABLE test (col)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);
        self::assertSame('TEXT', $schema->columns['col']->type);
    }

    #[Test]
    public function testParseColumnNotAutoIncrementByDefault(): void
    {
        $sql = 'CREATE TABLE test (id INTEGER NOT NULL, name TEXT)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);
        self::assertFalse($schema->columns['id']->autoIncrement);
    }

    #[Test]
    public function testParseDefaultLocaltimeValue(): void
    {
        $sql = 'CREATE TABLE test (t TIME DEFAULT LOCALTIME)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);
        self::assertSame('LOCALTIME', $schema->columns['t']->default);
    }

    #[Test]
    public function testParseDefaultLocaltimestampValue(): void
    {
        $sql = 'CREATE TABLE test (ts TIMESTAMP DEFAULT LOCALTIMESTAMP)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);
        self::assertSame('LOCALTIMESTAMP', $schema->columns['ts']->default);
    }

    #[Test]
    public function testParseDefaultCurrentDateValue(): void
    {
        $sql = 'CREATE TABLE test (d DATE DEFAULT CURRENT_DATE)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);
        self::assertSame('CURRENT_DATE', $schema->columns['d']->default);
    }

    #[Test]
    public function testParseDefaultCurrentTimeValue(): void
    {
        $sql = 'CREATE TABLE test (t TIME DEFAULT CURRENT_TIME)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);
        self::assertSame('CURRENT_TIME', $schema->columns['t']->default);
    }

    #[Test]
    public function testParseSerialWithPrecision(): void
    {
        $sql = 'CREATE TABLE test (id SERIAL(10) PRIMARY KEY, name TEXT)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);
        self::assertSame('INTEGER', $schema->columns['id']->type);
        self::assertTrue($schema->columns['id']->autoIncrement);
    }

    #[Test]
    public function testParseDefaultStringWithTrailingConstraints(): void
    {
        $sql = "CREATE TABLE test (name TEXT DEFAULT 'test' NOT NULL UNIQUE)";
        $schema = (new PostgreSqlSchemaParser())->parse($sql);
        self::assertSame('test', $schema->columns['name']->default);
    }

    #[Test]
    public function testParseCharWithLength(): void
    {
        $sql = 'CREATE TABLE test (code CHAR(3))';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);
        self::assertSame('CHAR', $schema->columns['code']->type);
        self::assertSame(3, $schema->columns['code']->length);
    }

    #[Test]
    public function testParseInlineComment(): void
    {
        $sql = "CREATE TABLE test (id INTEGER -- this is pk\n)";
        $schema = (new PostgreSqlSchemaParser())->parse($sql);
        self::assertSame('test', $schema->tableName);
    }

    #[Test]
    public function testParseLowercaseCreateTable(): void
    {
        $sql = 'create table users (id integer primary key, name text not null)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('users', $schema->tableName);
        self::assertSame('INTEGER', $schema->columns['id']->type);
        self::assertSame('TEXT', $schema->columns['name']->type);
        self::assertFalse($schema->columns['id']->nullable);
        self::assertFalse($schema->columns['name']->nullable);
    }

    #[Test]
    public function testParseLowercaseSerialType(): void
    {
        $sql = 'create table test (id serial primary key)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('INTEGER', $schema->columns['id']->type);
        self::assertTrue($schema->columns['id']->autoIncrement);
    }

    #[Test]
    public function testParseLowercaseBigserialType(): void
    {
        $sql = 'create table test (id bigserial primary key)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('BIGINT', $schema->columns['id']->type);
        self::assertTrue($schema->columns['id']->autoIncrement);
    }

    #[Test]
    public function testParseLowercaseSmallserialType(): void
    {
        $sql = 'create table test (id smallserial primary key)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('SMALLINT', $schema->columns['id']->type);
        self::assertTrue($schema->columns['id']->autoIncrement);
    }

    #[Test]
    public function testParseLowercaseIfNotExists(): void
    {
        $sql = 'create table if not exists users (id integer primary key)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('users', $schema->tableName);
    }

    #[Test]
    public function testParseLowercaseConstraints(): void
    {
        $sql = <<<'SQL'
            create table test (
                id integer,
                name text,
                foreign key (id) references other(id),
                unique (name),
                check (id > 0),
                constraint my_constraint unique (id, name),
                exclude using gist (id with &&)
            )
            SQL;

        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertCount(2, $schema->columns);
    }

    #[Test]
    public function testParseLowercaseTablePrimaryKey(): void
    {
        $sql = 'create table test (a integer, b text, primary key (a, b))';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame(['a', 'b'], $schema->primaryKeys);
        self::assertFalse($schema->columns['a']->nullable);
    }

    #[Test]
    public function testParseLowercaseMultiWordTypes(): void
    {
        $sql = <<<'SQL'
            create table test (
                col_dp double precision,
                col_tstz timestamp with time zone,
                col_ts timestamp without time zone,
                col_ttz time with time zone,
                col_t time without time zone,
                col_cv character varying(100)
            )
            SQL;

        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('DOUBLE PRECISION', $schema->columns['col_dp']->type);
        self::assertSame('TIMESTAMP WITH TIME ZONE', $schema->columns['col_tstz']->type);
        self::assertSame('TIMESTAMP WITHOUT TIME ZONE', $schema->columns['col_ts']->type);
        self::assertSame('TIME WITH TIME ZONE', $schema->columns['col_ttz']->type);
        self::assertSame('TIME WITHOUT TIME ZONE', $schema->columns['col_t']->type);
        self::assertSame('CHARACTER VARYING', $schema->columns['col_cv']->type);
        self::assertSame(100, $schema->columns['col_cv']->length);
    }

    #[Test]
    public function testParseLowercaseDecimalType(): void
    {
        $sql = 'create table test (val decimal(10, 2))';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('DECIMAL', $schema->columns['val']->type);
        self::assertSame(10, $schema->columns['val']->precision);
        self::assertSame(2, $schema->columns['val']->scale);
    }

    #[Test]
    public function testParseLowercaseNumericType(): void
    {
        $sql = 'create table test (val numeric(8))';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('NUMERIC', $schema->columns['val']->type);
        self::assertSame(8, $schema->columns['val']->precision);
    }

    #[Test]
    public function testParseLowercaseDecType(): void
    {
        $sql = 'create table test (val dec(6))';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame(6, $schema->columns['val']->precision);
    }

    #[Test]
    public function testParseLowercaseGeneratedColumn(): void
    {
        $sql = 'create table test (a integer, b integer, c integer generated always as (a + b) stored)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertTrue($schema->columns['c']->generated);
    }

    #[Test]
    public function testParseLowercaseDefaultValues(): void
    {
        $sql = <<<'SQL'
            create table test (
                col_null text default null,
                col_true boolean default true,
                col_false boolean default false,
                col_str text default 'hello',
                col_int integer default 42,
                col_float numeric(10,2) default 9.99,
                col_func uuid default gen_random_uuid(),
                col_cast jsonb default '{}'::jsonb,
                col_expr integer default (1+2),
                col_ts timestamp default current_timestamp,
                col_now timestamp default now(),
                col_date date default current_date,
                col_time time default current_time,
                col_lt time default localtime,
                col_lts timestamp default localtimestamp
            )
            SQL;

        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertNull($schema->columns['col_null']->default);
        self::assertTrue($schema->columns['col_true']->default);
        self::assertFalse($schema->columns['col_false']->default);
        self::assertSame('hello', $schema->columns['col_str']->default);
        self::assertSame(42, $schema->columns['col_int']->default);
        self::assertSame(9.99, $schema->columns['col_float']->default);
        self::assertSame('gen_random_uuid()', $schema->columns['col_func']->default);
        self::assertSame("'{}'::jsonb", $schema->columns['col_cast']->default);
        self::assertSame('(1+2)', $schema->columns['col_expr']->default);
        self::assertSame('current_timestamp', $schema->columns['col_ts']->default);
        self::assertSame('now()', $schema->columns['col_now']->default);
        self::assertSame('current_date', $schema->columns['col_date']->default);
        self::assertSame('current_time', $schema->columns['col_time']->default);
        self::assertSame('localtime', $schema->columns['col_lt']->default);
        self::assertSame('localtimestamp', $schema->columns['col_lts']->default);
    }

    #[Test]
    public function testParseLowercaseArrayTypes(): void
    {
        $sql = 'create table test (tags text[], ids integer[])';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('TEXT_ARRAY', $schema->columns['tags']->type);
        self::assertSame('INTEGER_ARRAY', $schema->columns['ids']->type);
    }

    #[Test]
    public function testParseLowercaseDefaultWithTrailingConstraints(): void
    {
        $sql = "create table test (name text default 'test' not null unique)";
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('test', $schema->columns['name']->default);
        self::assertFalse($schema->columns['name']->nullable);
    }

    #[Test]
    public function testParseWithLeadingTrailingWhitespace(): void
    {
        $sql = "  \n  create table test ( \n  id integer , \n  name text \n ) \n ";
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('test', $schema->tableName);
        self::assertCount(2, $schema->columns);
    }

    #[Test]
    public function testParseMixedCaseKeywords(): void
    {
        $sql = 'Create Table test (id Integer Primary Key, name Varchar(100) Not Null)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('test', $schema->tableName);
        self::assertSame('INTEGER', $schema->columns['id']->type);
        self::assertSame('VARCHAR', $schema->columns['name']->type);
        self::assertFalse($schema->columns['id']->nullable);
        self::assertFalse($schema->columns['name']->nullable);
    }

    #[Test]
    public function testParseDefaultStringWithConstraintsAfter(): void
    {
        $sql = "CREATE TABLE test (val TEXT DEFAULT 'hello' NOT NULL CHECK (val <> ''))";
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('hello', $schema->columns['val']->default);
    }

    #[Test]
    public function testParseConstraintBeforeColumn(): void
    {
        $sql = <<<'SQL'
            CREATE TABLE test (
                id INTEGER,
                PRIMARY KEY (id),
                name TEXT NOT NULL
            )
            SQL;

        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertCount(2, $schema->columns);
        self::assertArrayHasKey('id', $schema->columns);
        self::assertArrayHasKey('name', $schema->columns);
        self::assertSame('TEXT', $schema->columns['name']->type);
        self::assertFalse($schema->columns['name']->nullable);
    }

    #[Test]
    public function testParseMultipleConstraintsBetweenColumns(): void
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

        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertCount(4, $schema->columns);
        self::assertArrayHasKey('name', $schema->columns);
        self::assertArrayHasKey('email', $schema->columns);
        self::assertArrayHasKey('age', $schema->columns);
        self::assertSame('INTEGER', $schema->columns['age']->type);
    }

    #[Test]
    public function testParseDefaultExpressionWithOnlyOpeningParen(): void
    {
        $sql = "CREATE TABLE test (val TEXT DEFAULT 'value(test')";
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('value(test', $schema->columns['val']->default);
    }

    #[Test]
    public function testParseColumnWithNoTypeReturnsTextViaExtractType(): void
    {
        $sql = 'CREATE TABLE test (col1, col2 INTEGER)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('TEXT', $schema->columns['col1']->type);
        self::assertSame('INTEGER', $schema->columns['col2']->type);
    }

    #[Test]
    public function testParseDefaultExpressionWrappedInParens(): void
    {
        $sql = 'CREATE TABLE test (val INTEGER DEFAULT (10 * 2))';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('(10 * 2)', $schema->columns['val']->default);
    }

    #[Test]
    public function testParseDefaultExpressionOnlyClosingParen(): void
    {
        $sql = "CREATE TABLE test (val TEXT DEFAULT 'test)')";
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('test)', $schema->columns['val']->default);
    }

    #[Test]
    public function testParseDefaultTypeCastIsPreserved(): void
    {
        $sql = "CREATE TABLE test (val TEXT DEFAULT 'hello'::text)";
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame("'hello'::text", $schema->columns['val']->default);
    }

    #[Test]
    public function testParseDefaultFunctionCallPreserved(): void
    {
        $sql = 'CREATE TABLE test (id UUID DEFAULT uuid_generate_v4())';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('uuid_generate_v4()', $schema->columns['id']->default);
    }

    #[Test]
    public function testParseTrimsDefinitionsInSplitColumnDefinitions(): void
    {
        $sql = 'CREATE TABLE test (  id INTEGER  ,  name TEXT  )';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertCount(2, $schema->columns);
        self::assertArrayHasKey('id', $schema->columns);
        self::assertArrayHasKey('name', $schema->columns);
    }

    #[Test]
    public function testParseColumnDefinitionTrimsRest(): void
    {
        $sql = 'CREATE TABLE test (id   INTEGER   NOT NULL)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('INTEGER', $schema->columns['id']->type);
        self::assertFalse($schema->columns['id']->nullable);
    }

    #[Test]
    public function testParseDefaultTrimsValue(): void
    {
        $sql = "CREATE TABLE test (val TEXT DEFAULT 'trimmed' NOT NULL)";
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('trimmed', $schema->columns['val']->default);
        self::assertFalse($schema->columns['val']->nullable);
    }

    #[Test]
    public function testParseIsTableConstraintWithLeadingWhitespace(): void
    {
        $sql = "CREATE TABLE test (id INTEGER, \n  PRIMARY KEY (id))";
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertCount(1, $schema->columns);
    }

    #[Test]
    public function testParseIsDecimalTypeCaseInsensitive(): void
    {
        $sql = 'CREATE TABLE test (val decimal(5))';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('DECIMAL', $schema->columns['val']->type);
        self::assertSame(5, $schema->columns['val']->precision);
        self::assertSame(0, $schema->columns['val']->scale);
    }

    #[Test]
    public function testParseDefaultCurrentTimestampNotPartOfLargerWord(): void
    {
        $sql = "CREATE TABLE test (ts TEXT DEFAULT 'NOT_CURRENT_TIMESTAMP_EXTRA')";
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('NOT_CURRENT_TIMESTAMP_EXTRA', $schema->columns['ts']->default);
    }

    #[Test]
    public function testParseNormalizeSqlRemovesBlockComments(): void
    {
        $sql = 'CREATE TABLE /* block comment */ test (id /* another */ INTEGER)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('test', $schema->tableName);
        self::assertSame('INTEGER', $schema->columns['id']->type);
    }

    #[Test]
    public function testParseNormalizeSqlTrimsWhitespace(): void
    {
        $sql = "\n\n   CREATE TABLE test (id INTEGER)   \n\n";
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('test', $schema->tableName);
    }

    #[Test]
    public function testParseDefaultStringWithDoubleQuotes(): void
    {
        $sql = 'CREATE TABLE test (name TEXT DEFAULT "value_here")';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame('value_here', $schema->columns['name']->default);
    }

    #[Test]
    public function testParseDefaultReferencesConstraint(): void
    {
        $sql = 'CREATE TABLE test (user_id INTEGER DEFAULT 1 REFERENCES users(id))';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame(1, $schema->columns['user_id']->default);
    }

    #[Test]
    public function testParseDefaultWithGeneratedKeyword(): void
    {
        $sql = 'CREATE TABLE test (val INTEGER DEFAULT 5, gen INTEGER GENERATED ALWAYS AS (val * 2) STORED)';
        $schema = (new PostgreSqlSchemaParser())->parse($sql);

        self::assertSame(5, $schema->columns['val']->default);
        self::assertTrue($schema->columns['gen']->generated);
    }

    #[Test]
    public function testParseParensEqualStartReturnsNull(): void
    {
        $this->expectException(SchemaParseException::class);
        (new PostgreSqlSchemaParser())->parse('CREATE TABLE test )( ');
    }
}
