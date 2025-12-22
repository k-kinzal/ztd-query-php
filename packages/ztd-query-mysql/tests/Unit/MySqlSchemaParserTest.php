<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\Contract\SchemaParserContractTest;
use ZtdQuery\Platform\MySql\MySqlParser;
use ZtdQuery\Platform\MySql\MySqlSchemaParser;
use ZtdQuery\Platform\SchemaParser;
use ZtdQuery\Schema\ColumnTypeFamily;

#[CoversClass(MySqlSchemaParser::class)]
#[UsesClass(MySqlParser::class)]
final class MySqlSchemaParserTest extends SchemaParserContractTest
{
    protected function createParser(): SchemaParser
    {
        return new MySqlSchemaParser(new MySqlParser());
    }

    protected function validCreateTableSql(): string
    {
        return <<<'SQL'
            CREATE TABLE users (
                id INT NOT NULL AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY email_unique (email)
            )
            SQL;
    }

    protected function nonCreateTableSql(): string
    {
        return 'SELECT * FROM users WHERE id = 1';
    }

    public function testParseSimpleCreateTable(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);

        $sql = 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255) NOT NULL)';
        $definition = $schemaParser->parse($sql);

        self::assertNotNull($definition);
        self::assertSame(['id', 'name'], $definition->columns);
        self::assertSame(['id'], $definition->primaryKeys);
        self::assertContains('id', $definition->notNullColumns);
    }

    public function testParseReturnsNullForNonCreateStatement(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);

        $definition = $schemaParser->parse('SELECT 1');
        self::assertNull($definition);
    }

    public function testParseReturnsNullForEmptySql(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);

        $definition = $schemaParser->parse('');
        self::assertNull($definition);
    }

    public function testParseCompositePrimaryKey(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);

        $sql = 'CREATE TABLE order_items (order_id INT NOT NULL, product_id INT NOT NULL, quantity INT, PRIMARY KEY (order_id, product_id))';
        $definition = $schemaParser->parse($sql);

        self::assertNotNull($definition);
        self::assertSame(['order_id', 'product_id'], $definition->primaryKeys);
    }

    public function testParseUniqueConstraint(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);

        $sql = 'CREATE TABLE users (id INT PRIMARY KEY, email VARCHAR(255) UNIQUE)';
        $definition = $schemaParser->parse($sql);

        self::assertNotNull($definition);
        self::assertNotEmpty($definition->uniqueConstraints);
    }

    public function testParseColumnTypes(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);

        $sql = 'CREATE TABLE t (a INT, b VARCHAR(255), c DECIMAL(10,2))';
        $definition = $schemaParser->parse($sql);

        self::assertNotNull($definition);
        self::assertSame('INT', $definition->columnTypes['a']);
        self::assertSame('VARCHAR(255)', $definition->columnTypes['b']);
        self::assertSame('DECIMAL(10,2)', $definition->columnTypes['c']);
    }

    public function testParseTypedColumnsIntegerFamily(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);

        $sql = 'CREATE TABLE t (a INT, b TINYINT, c SMALLINT, d MEDIUMINT, e BIGINT, f INTEGER)';
        $definition = $schemaParser->parse($sql);

        self::assertNotNull($definition);
        self::assertSame(ColumnTypeFamily::INTEGER, $definition->typedColumns['a']->family);
        self::assertSame(ColumnTypeFamily::INTEGER, $definition->typedColumns['b']->family);
        self::assertSame(ColumnTypeFamily::INTEGER, $definition->typedColumns['c']->family);
        self::assertSame(ColumnTypeFamily::INTEGER, $definition->typedColumns['d']->family);
        self::assertSame(ColumnTypeFamily::INTEGER, $definition->typedColumns['e']->family);
        self::assertSame(ColumnTypeFamily::INTEGER, $definition->typedColumns['f']->family);
    }

    public function testParseTypedColumnsDecimalFamily(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);

        $sql = 'CREATE TABLE t (a DECIMAL(10,2), b NUMERIC(5,3))';
        $definition = $schemaParser->parse($sql);

        self::assertNotNull($definition);
        self::assertSame(ColumnTypeFamily::DECIMAL, $definition->typedColumns['a']->family);
        self::assertSame(ColumnTypeFamily::DECIMAL, $definition->typedColumns['b']->family);
    }

    public function testParseTypedColumnsFloatDoubleFamily(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);

        $sql = 'CREATE TABLE t (a FLOAT, b DOUBLE, c REAL)';
        $definition = $schemaParser->parse($sql);

        self::assertNotNull($definition);
        self::assertSame(ColumnTypeFamily::FLOAT, $definition->typedColumns['a']->family);
        self::assertSame(ColumnTypeFamily::DOUBLE, $definition->typedColumns['b']->family);
        self::assertSame(ColumnTypeFamily::DOUBLE, $definition->typedColumns['c']->family);
    }

    public function testParseTypedColumnsBooleanFamily(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);

        $sql = 'CREATE TABLE t (a BOOL, b BOOLEAN)';
        $definition = $schemaParser->parse($sql);

        self::assertNotNull($definition);
        self::assertSame(ColumnTypeFamily::BOOLEAN, $definition->typedColumns['a']->family);
        self::assertSame(ColumnTypeFamily::BOOLEAN, $definition->typedColumns['b']->family);
    }

    public function testParseTypedColumnsDatetimeFamily(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);

        $sql = 'CREATE TABLE t (a DATE, b TIME, c DATETIME, d TIMESTAMP)';
        $definition = $schemaParser->parse($sql);

        self::assertNotNull($definition);
        self::assertSame(ColumnTypeFamily::DATE, $definition->typedColumns['a']->family);
        self::assertSame(ColumnTypeFamily::TIME, $definition->typedColumns['b']->family);
        self::assertSame(ColumnTypeFamily::DATETIME, $definition->typedColumns['c']->family);
        self::assertSame(ColumnTypeFamily::TIMESTAMP, $definition->typedColumns['d']->family);
    }

    public function testParseTypedColumnsJsonFamily(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);

        $sql = 'CREATE TABLE t (a JSON)';
        $definition = $schemaParser->parse($sql);

        self::assertNotNull($definition);
        self::assertSame(ColumnTypeFamily::JSON, $definition->typedColumns['a']->family);
    }

    public function testParseTypedColumnsBinaryFamily(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);

        $sql = 'CREATE TABLE t (a BINARY, b VARBINARY(255), c BLOB, d TINYBLOB, e MEDIUMBLOB, f LONGBLOB)';
        $definition = $schemaParser->parse($sql);

        self::assertNotNull($definition);
        self::assertSame(ColumnTypeFamily::BINARY, $definition->typedColumns['a']->family);
        self::assertSame(ColumnTypeFamily::BINARY, $definition->typedColumns['b']->family);
        self::assertSame(ColumnTypeFamily::BINARY, $definition->typedColumns['c']->family);
        self::assertSame(ColumnTypeFamily::BINARY, $definition->typedColumns['d']->family);
        self::assertSame(ColumnTypeFamily::BINARY, $definition->typedColumns['e']->family);
        self::assertSame(ColumnTypeFamily::BINARY, $definition->typedColumns['f']->family);
    }

    public function testParseTypedColumnsStringFamily(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);

        $sql = "CREATE TABLE t (a CHAR(10), b VARCHAR(255), c ENUM('a','b'), d SET('x','y'))";
        $definition = $schemaParser->parse($sql);

        self::assertNotNull($definition);
        self::assertSame(ColumnTypeFamily::STRING, $definition->typedColumns['a']->family);
        self::assertSame(ColumnTypeFamily::STRING, $definition->typedColumns['b']->family);
        self::assertSame(ColumnTypeFamily::STRING, $definition->typedColumns['c']->family);
        self::assertSame(ColumnTypeFamily::STRING, $definition->typedColumns['d']->family);
    }

    public function testParseTypedColumnsTextFamily(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);

        $sql = 'CREATE TABLE t (a TEXT, b TINYTEXT, c MEDIUMTEXT, d LONGTEXT)';
        $definition = $schemaParser->parse($sql);

        self::assertNotNull($definition);
        self::assertSame(ColumnTypeFamily::TEXT, $definition->typedColumns['a']->family);
        self::assertSame(ColumnTypeFamily::TEXT, $definition->typedColumns['b']->family);
        self::assertSame(ColumnTypeFamily::TEXT, $definition->typedColumns['c']->family);
        self::assertSame(ColumnTypeFamily::TEXT, $definition->typedColumns['d']->family);
    }

    public function testParseTypedColumnsYearAndBit(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);

        $sql = 'CREATE TABLE t (a YEAR, b BIT)';
        $definition = $schemaParser->parse($sql);

        self::assertNotNull($definition);
        self::assertSame(ColumnTypeFamily::INTEGER, $definition->typedColumns['a']->family);
        self::assertSame(ColumnTypeFamily::INTEGER, $definition->typedColumns['b']->family);
    }

    public function testParseTypedColumnsNativeType(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);

        $sql = 'CREATE TABLE t (a DECIMAL(10,2))';
        $definition = $schemaParser->parse($sql);

        self::assertNotNull($definition);
        self::assertSame('DECIMAL(10,2)', $definition->typedColumns['a']->nativeType);
    }

    public function testParseNotNullColumn(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);

        $sql = 'CREATE TABLE t (a INT NOT NULL, b INT)';
        $definition = $schemaParser->parse($sql);

        self::assertNotNull($definition);
        self::assertContains('a', $definition->notNullColumns);
        self::assertNotContains('b', $definition->notNullColumns);
    }

    public function testParsePrimaryKeyImpliesNotNull(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);

        $sql = 'CREATE TABLE t (id INT PRIMARY KEY, name VARCHAR(255))';
        $definition = $schemaParser->parse($sql);

        self::assertNotNull($definition);
        self::assertContains('id', $definition->notNullColumns);
        self::assertNotContains('name', $definition->notNullColumns);
    }

    public function testParseUniqueKeyConstraint(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);

        $sql = 'CREATE TABLE t (id INT, email VARCHAR(255), UNIQUE KEY uk_email (email))';
        $definition = $schemaParser->parse($sql);

        self::assertNotNull($definition);
        self::assertArrayHasKey('uk_email', $definition->uniqueConstraints);
        self::assertSame(['email'], $definition->uniqueConstraints['uk_email']);
    }

    public function testParseUniqueWithoutName(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);

        $sql = 'CREATE TABLE t (id INT, email VARCHAR(255), UNIQUE (email))';
        $definition = $schemaParser->parse($sql);

        self::assertNotNull($definition);
        self::assertNotEmpty($definition->uniqueConstraints);
    }

    public function testParseUnknownTypeFamily(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);

        $sql = 'CREATE TABLE t (a GEOMETRY)';
        $definition = $schemaParser->parse($sql);

        self::assertNotNull($definition);
        self::assertSame(ColumnTypeFamily::UNKNOWN, $definition->typedColumns['a']->family);
    }

    public function testParseColumnTypeWithBackticks(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);

        $sql = 'CREATE TABLE t (`id` INT, `name` VARCHAR(255))';
        $definition = $schemaParser->parse($sql);

        self::assertNotNull($definition);
        self::assertSame(['id', 'name'], $definition->columns);
    }

    public function testParseNativeTypeIncludesParameters(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);

        $sql = 'CREATE TABLE t (a VARCHAR(100), b INT)';
        $definition = $schemaParser->parse($sql);

        self::assertNotNull($definition);
        self::assertSame('VARCHAR(100)', $definition->typedColumns['a']->nativeType);
        self::assertSame('INT', $definition->typedColumns['b']->nativeType);
    }

    public function testParseColumnNameStripsBackticks(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);

        $sql = 'CREATE TABLE t (`my_col` INT)';
        $definition = $schemaParser->parse($sql);

        self::assertNotNull($definition);
        self::assertSame(['my_col'], $definition->columns);
        self::assertArrayHasKey('my_col', $definition->columnTypes);
        self::assertArrayHasKey('my_col', $definition->typedColumns);
    }

    public function testParsePrimaryKeyColumnNameStripsBackticks(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);

        $sql = 'CREATE TABLE t (`id` INT, PRIMARY KEY (`id`))';
        $definition = $schemaParser->parse($sql);

        self::assertNotNull($definition);
        self::assertContains('id', $definition->primaryKeys);
    }

    public function testParseUniqueKeyColumnNameStripsBackticks(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);

        $sql = 'CREATE TABLE t (`id` INT, `email` VARCHAR(255), UNIQUE KEY uk (`email`))';
        $definition = $schemaParser->parse($sql);

        self::assertNotNull($definition);
        self::assertNotEmpty($definition->uniqueConstraints);
        $matches = array_filter($definition->uniqueConstraints, static fn (array $cols): bool => in_array('email', $cols, true));
        self::assertNotEmpty($matches);
    }

    public function testParseUniqueColumnOption(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);

        $sql = 'CREATE TABLE t (id INT PRIMARY KEY, code VARCHAR(10) UNIQUE)';
        $definition = $schemaParser->parse($sql);

        self::assertNotNull($definition);
        $matches = array_filter($definition->uniqueConstraints, static fn (array $cols): bool => in_array('code', $cols, true));
        self::assertNotEmpty($matches);
        self::assertArrayHasKey('code_UNIQUE', $matches);
    }

    public function testParseNotNullWithPrimaryKeyImplied(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);

        $sql = 'CREATE TABLE t (id INT PRIMARY KEY NOT NULL, name TEXT)';
        $definition = $schemaParser->parse($sql);

        self::assertNotNull($definition);
        self::assertContains('id', $definition->notNullColumns);
        $count = count(array_filter($definition->notNullColumns, static fn (string $c): bool => $c === 'id'));
        self::assertSame(1, $count);
    }

    public function testParseTypeNameUppercased(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);

        $sql = 'CREATE TABLE t (a int, b varchar(50))';
        $definition = $schemaParser->parse($sql);

        self::assertNotNull($definition);
        self::assertSame('INT', $definition->columnTypes['a']);
        self::assertSame('VARCHAR(50)', $definition->columnTypes['b']);
    }

    public function testParseMultipleUnnamedUniqueConstraints(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);

        $sql = 'CREATE TABLE t (id INT, a INT, b INT, UNIQUE (a), UNIQUE (b))';
        $definition = $schemaParser->parse($sql);

        self::assertNotNull($definition);
        self::assertCount(2, $definition->uniqueConstraints);
        $keys = array_keys($definition->uniqueConstraints);
        self::assertNotSame($keys[0], $keys[1]);
        self::assertStringContainsString('unique_', $keys[0]);
        self::assertStringContainsString('unique_', $keys[1]);
    }

    public function testParseCompositeUniqueKey(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);

        $sql = 'CREATE TABLE t (a INT, b INT, UNIQUE KEY uq_ab (a, b))';
        $definition = $schemaParser->parse($sql);

        self::assertNotNull($definition);
        self::assertArrayHasKey('uq_ab', $definition->uniqueConstraints);
        self::assertSame(['a', 'b'], $definition->uniqueConstraints['uq_ab']);
    }

    public function testParsePrimaryKeyBacktickStripping(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);

        $sql = 'CREATE TABLE t (`col_a` INT, `col_b` INT, PRIMARY KEY (`col_a`, `col_b`))';
        $definition = $schemaParser->parse($sql);

        self::assertNotNull($definition);
        self::assertSame(['col_a', 'col_b'], $definition->primaryKeys);
        self::assertNotContains('`col_a`', $definition->primaryKeys);
    }

    public function testParseUniqueKeyBacktickStripping(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);

        $sql = 'CREATE TABLE t (`id` INT, `email` VARCHAR(255), UNIQUE KEY uk_email (`email`))';
        $definition = $schemaParser->parse($sql);

        self::assertNotNull($definition);
        self::assertArrayHasKey('uk_email', $definition->uniqueConstraints);
        self::assertSame(['email'], $definition->uniqueConstraints['uk_email']);
        self::assertNotContains('`email`', $definition->uniqueConstraints['uk_email']);
    }

    public function testParseColumnNameBacktickStrippedFromColumnTypes(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);

        $sql = 'CREATE TABLE t (`my_id` INT)';
        $definition = $schemaParser->parse($sql);

        self::assertNotNull($definition);
        self::assertArrayHasKey('my_id', $definition->columnTypes);
        self::assertArrayNotHasKey('`my_id`', $definition->columnTypes);
    }

    public function testParseTypeUppercasedInFamilyMapping(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);

        $sql = 'CREATE TABLE t (a varchar(100))';
        $definition = $schemaParser->parse($sql);

        self::assertNotNull($definition);
        self::assertSame(ColumnTypeFamily::STRING, $definition->typedColumns['a']->family);
        self::assertSame('VARCHAR(100)', $definition->typedColumns['a']->nativeType);
    }

    public function testParseMultipleUnnamedUniqueConstraintsKeyNamesSequential(): void
    {
        $parser = new MySqlParser();
        $schemaParser = new MySqlSchemaParser($parser);

        $sql = 'CREATE TABLE t (id INT, a INT, b INT, UNIQUE (a), UNIQUE (b))';
        $definition = $schemaParser->parse($sql);

        self::assertNotNull($definition);
        $keys = array_keys($definition->uniqueConstraints);
        self::assertSame('unique_0', $keys[0]);
        self::assertSame('unique_1', $keys[1]);
    }
}
