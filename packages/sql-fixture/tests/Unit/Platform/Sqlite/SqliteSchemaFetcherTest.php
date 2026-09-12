<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Sqlite;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\Sqlite\SqliteSchemaFetcher;
use SqlFixture\Platform\Sqlite\SqliteSchemaParser;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\TableSchema;

#[CoversClass(SqliteSchemaFetcher::class)]
#[UsesClass(SqliteSchemaParser::class)]
#[UsesClass(ColumnDefinition::class)]
#[UsesClass(TableSchema::class)]
#[CoversClass(\SqlFixture\Platform\Sqlite\Schema\CreateTableQuery::class)]
#[CoversClass(\SqlFixture\Platform\Sqlite\Schema\PragmaColumn::class)]
#[CoversClass(\SqlFixture\Platform\Sqlite\Schema\PragmaSchema::class)]
#[UsesClass(\SqlFixture\Schema\SchemaFetcherInterface::class)]
#[UsesClass(\SqlFixture\Schema\SchemaParseException::class)]
#[UsesClass(\SqlFixture\Schema\SchemaParserInterface::class)]
#[UsesClass(\SqlFixture\Platform\Sqlite\Schema\ColumnParser::class)]
#[UsesClass(\SqlFixture\Platform\Sqlite\Schema\DefaultExpression::class)]
#[UsesClass(\SqlFixture\Platform\Sqlite\Schema\DefinitionList::class)]
#[UsesClass(\SqlFixture\Platform\Sqlite\Schema\TableSyntax::class)]
#[UsesClass(\SqlFixture\Platform\Sqlite\Schema\TypeDeclaration::class)]
#[UsesClass(\SqlFixture\Schema\DefinitionSegments::class)]
#[UsesClass(\SqlFixture\Schema\TypeShape::class)]
final class SqliteSchemaFetcherTest extends TestCase
{
    #[Test]
    public function testFetchSchemaFromSqliteTable(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE products (id INTEGER PRIMARY KEY, name TEXT NOT NULL, price REAL)');

        $fetcher = new SqliteSchemaFetcher();
        $schema = $fetcher->fetchSchema($pdo, 'products');

        self::assertSame('products', $schema->tableName);
        self::assertTrue($schema->hasColumn('id'));
        self::assertTrue($schema->hasColumn('name'));
        self::assertTrue($schema->hasColumn('price'));
    }

    #[Test]
    public function testFetchSchemaWithVarcharLength(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY, name VARCHAR(100) NOT NULL)');

        $fetcher = new SqliteSchemaFetcher();
        $schema = $fetcher->fetchSchema($pdo, 'items');

        self::assertSame('VARCHAR', $schema->columns['name']->type);
        self::assertSame(100, $schema->columns['name']->length);
        self::assertFalse($schema->columns['name']->nullable);
    }

    #[Test]
    public function testFetchSchemaWithDecimalPrecisionScale(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE money (id INTEGER PRIMARY KEY, amount DECIMAL(10, 2))');

        $fetcher = new SqliteSchemaFetcher();
        $schema = $fetcher->fetchSchema($pdo, 'money');

        self::assertSame('DECIMAL', $schema->columns['amount']->type);
        self::assertSame(10, $schema->columns['amount']->precision);
        self::assertSame(2, $schema->columns['amount']->scale);
    }

    #[Test]
    public function testFetchSchemaWithDefaultValues(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec("CREATE TABLE defaults (
            id INTEGER PRIMARY KEY,
            count INTEGER DEFAULT 42,
            name TEXT DEFAULT 'hello',
            nullable TEXT DEFAULT NULL,
            price REAL DEFAULT 9.99
        )");

        $fetcher = new SqliteSchemaFetcher();
        $schema = $fetcher->fetchSchema($pdo, 'defaults');

        self::assertSame(42, $schema->columns['count']->default);
        self::assertSame('hello', $schema->columns['name']->default);
        self::assertNull($schema->columns['nullable']->default);
        self::assertSame(9.99, $schema->columns['price']->default);
    }

    #[Test]
    public function testFetchSchemaWithNullableColumns(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE test (id INTEGER NOT NULL, name TEXT)');

        $fetcher = new SqliteSchemaFetcher();
        $schema = $fetcher->fetchSchema($pdo, 'test');

        self::assertFalse($schema->columns['id']->nullable);
        self::assertTrue($schema->columns['name']->nullable);
    }

    #[Test]
    public function testFetchSchemaWithAutoincrement(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE test (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');

        $fetcher = new SqliteSchemaFetcher();
        $schema = $fetcher->fetchSchema($pdo, 'test');

        self::assertTrue($schema->columns['id']->autoIncrement);
    }

    #[Test]
    public function testFetchSchemaWithCustomParser(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE test (id INTEGER PRIMARY KEY, name TEXT)');

        $parser = new SqliteSchemaParser();
        $fetcher = new SqliteSchemaFetcher($parser);
        $schema = $fetcher->fetchSchema($pdo, 'test');

        self::assertSame('test', $schema->tableName);
    }

    #[Test]
    public function testFetchSchemaWithDefaultCurrentTimestamp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE test (id INTEGER PRIMARY KEY, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');

        $fetcher = new SqliteSchemaFetcher();
        $schema = $fetcher->fetchSchema($pdo, 'test');

        self::assertSame('CURRENT_TIMESTAMP', $schema->columns['created_at']->default);
    }
}
