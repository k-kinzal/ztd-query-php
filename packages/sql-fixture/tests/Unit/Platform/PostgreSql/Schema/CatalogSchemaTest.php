<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\PostgreSql\Schema;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\PostgreSql\Schema\CatalogSchema as Subject;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\Medium]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\PostgreSql\Schema\CatalogColumn::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\PostgreSql\Schema\CatalogQuery::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\TableSchema::class)]
final class CatalogSchemaTest extends TestCase
{
    public function testFetchSchemaFromInformationSchemaRetainsColumnMetadata(): void
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
        $schema = (new Subject())->fetchSchemaFromInformationSchema($pdo, $namespace . '.users');
        self::assertSame('users', $schema->tableName);
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
        $pdo->exec('CREATE TEMPORARY TABLE prices (id SERIAL, amount NUMERIC(7,2), note TEXT)');
        $statement = $pdo->query('SELECT pg_my_temp_schema()::regnamespace::text');
        self::assertNotFalse($statement);
        $namespace = $statement->fetchColumn();
        self::assertIsString($namespace);
        $schema = (new Subject())->fetchSchemaFromInformationSchema($pdo, $namespace . '.prices');
        self::assertSame('prices', $schema->tableName);
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
    }
}
