<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\PostgreSql\Schema;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFixture\Platform\PostgreSql\Schema\CatalogExpression;
use SqlFixture\Platform\PostgreSql\Schema\CatalogSchema as Subject;
use SqlParser\PostgreSql\PostgreSqlParser;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\Medium]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\TableSchema::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\NodeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\NumericLiteral::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\QuotedText::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\PostgreSql\Schema\CatalogColumn::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(CatalogExpression::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\PostgreSql\Schema\CatalogQuery::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\PostgreSql\Schema\DefaultExpression::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\PostgreSql\Schema\StringLiteral::class)]
final class CatalogSchemaTest extends TestCase
{
    public function testFetchSchemaRetainsColumnMetadataAndPrimaryKey(): void
    {
        $pdo = new PDO(
            (string) getenv('SQL_FIXTURE_PGSQL_DSN'),
            (string) getenv('SQL_FIXTURE_PGSQL_USER'),
            (string) getenv('SQL_FIXTURE_PGSQL_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_STRINGIFY_FETCHES => true],
        );
        $pdo->exec('CREATE TEMPORARY TABLE users (id INTEGER PRIMARY KEY, name VARCHAR(30) NOT NULL DEFAULT \'ready\')');
        $statement = $pdo->query('SELECT pg_my_temp_schema()::regnamespace::text');
        self::assertNotFalse($statement);
        $namespace = $statement->fetchColumn();
        self::assertIsString($namespace);
        $schema = (new Subject(new CatalogExpression(new PostgreSqlParser())))->fetchSchema($pdo, $namespace . '.users');
        self::assertSame('users', $schema->tableName);
        self::assertSame(['id'], $schema->primaryKeys);
        self::assertFalse($schema->columns['id']->nullable);
        self::assertSame(30, $schema->columns['name']->length);
        self::assertSame('ready', $schema->columns['name']->default);
    }

    public function testFetchSchemaPreservesNullableSerialAndDecimalMetadata(): void
    {
        $pdo = new PDO(
            (string) getenv('SQL_FIXTURE_PGSQL_DSN'),
            (string) getenv('SQL_FIXTURE_PGSQL_USER'),
            (string) getenv('SQL_FIXTURE_PGSQL_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_STRINGIFY_FETCHES => true],
        );
        $pdo->exec('CREATE TEMPORARY TABLE prices (id SERIAL, amount NUMERIC(7,2), note TEXT, total NUMERIC GENERATED ALWAYS AS (amount * 2) STORED, seq BIGINT GENERATED ALWAYS AS IDENTITY)');
        $statement = $pdo->query('SELECT pg_my_temp_schema()::regnamespace::text');
        self::assertNotFalse($statement);
        $namespace = $statement->fetchColumn();
        self::assertIsString($namespace);
        $schema = (new Subject(new CatalogExpression(new PostgreSqlParser())))->fetchSchema($pdo, $namespace . '.prices');
        self::assertSame('prices', $schema->tableName);
        self::assertSame([], $schema->primaryKeys);
        self::assertTrue($schema->columns['id']->autoIncrement);
        self::assertFalse($schema->columns['id']->nullable);
        self::assertNull($schema->columns['id']->default);
        self::assertTrue($schema->columns['amount']->nullable);
        self::assertFalse($schema->columns['amount']->autoIncrement);
        self::assertNull($schema->columns['amount']->length);
        self::assertSame(7, $schema->columns['amount']->precision);
        self::assertSame(2, $schema->columns['amount']->scale);
        self::assertFalse($schema->columns['amount']->unsigned);
        self::assertFalse($schema->columns['amount']->generated);
        self::assertNull($schema->columns['note']->precision);
        self::assertNull($schema->columns['note']->scale);
        self::assertNull($schema->columns['note']->default);
        self::assertTrue($schema->columns['total']->generated);
        self::assertTrue($schema->columns['seq']->autoIncrement);
    }

    public function testFetchSchemaRejectsAnUnknownTable(): void
    {
        $pdo = new PDO(
            (string) getenv('SQL_FIXTURE_PGSQL_DSN'),
            (string) getenv('SQL_FIXTURE_PGSQL_USER'),
            (string) getenv('SQL_FIXTURE_PGSQL_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false],
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Table not found: missing_table');
        (new Subject(new CatalogExpression(new PostgreSqlParser())))->fetchSchema($pdo, 'missing_table');
    }
}
