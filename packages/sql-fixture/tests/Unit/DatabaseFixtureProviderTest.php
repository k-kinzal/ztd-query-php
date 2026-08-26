<?php

declare(strict_types=1);

namespace Tests\Unit;

use Faker\Factory;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\DatabaseFixtureProvider;
use SqlFixture\FixtureGenerator;
use SqlFixture\Platform\PlatformFactory;
use SqlFixture\Platform\Sqlite\SqliteSchemaFetcher;
use SqlFixture\Platform\Sqlite\SqliteSchemaParser;
use SqlFixture\Platform\Sqlite\SqliteTypeMapper;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\TableSchema;

#[CoversClass(DatabaseFixtureProvider::class)]
#[UsesClass(FixtureGenerator::class)]
#[UsesClass(PlatformFactory::class)]
#[UsesClass(SqliteSchemaFetcher::class)]
#[UsesClass(SqliteSchemaParser::class)]
#[UsesClass(SqliteTypeMapper::class)]
#[UsesClass(ColumnDefinition::class)]
#[UsesClass(TableSchema::class)]
final class DatabaseFixtureProviderTest extends TestCase
{
    #[Test]
    public function testFixtureGeneratesArrayFromSqliteTable(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT)');

        $faker = Factory::create();
        $provider = new DatabaseFixtureProvider($faker, $pdo);

        $result = $provider->fixture('users');
        self::assertArrayHasKey('name', $result);
    }

    #[Test]
    public function testClearCacheAllowsRefetch(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT)');

        $faker = Factory::create();
        $provider = new DatabaseFixtureProvider($faker, $pdo);

        $provider->fixture('items');
        $provider->clearCache();
        $result = $provider->fixture('items');
        self::assertArrayHasKey('title', $result);
    }

    #[Test]
    public function testGetDriverReturnsSqlite(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $faker = Factory::create();
        $provider = new DatabaseFixtureProvider($faker, $pdo);

        self::assertSame('sqlite', $provider->getDriver());
    }

    #[Test]
    public function fixtureWithCustomTypeMapper(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE test (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');

        $faker = Factory::create();
        $typeMapper = new SqliteTypeMapper();
        $provider = new DatabaseFixtureProvider($faker, $pdo, typeMapper: $typeMapper);

        $data = $provider->fixture('test');
        self::assertArrayHasKey('name', $data);
    }

    #[Test]
    public function fixtureWithCustomSchemaFetcher(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE test (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');

        $faker = Factory::create();
        $fetcher = new SqliteSchemaFetcher();
        $provider = new DatabaseFixtureProvider($faker, $pdo, schemaFetcher: $fetcher);

        $data = $provider->fixture('test');
        self::assertArrayHasKey('name', $data);
    }

    #[Test]
    public function schemaCacheWorksOnSecondCall(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, val TEXT NOT NULL)');

        $faker = Factory::create();
        $provider = new DatabaseFixtureProvider($faker, $pdo);

        $data1 = $provider->fixture('items');
        $data2 = $provider->fixture('items');

        self::assertArrayHasKey('val', $data1);
        self::assertArrayHasKey('val', $data2);
    }

    #[Test]
    public function fixtureNormalizesQuotedTableNames(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE products (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');

        $faker = Factory::create();
        $provider = new DatabaseFixtureProvider($faker, $pdo);

        $data = $provider->fixture('products');
        self::assertArrayHasKey('name', $data);
    }

    #[Test]
    public function fixtureWithOverrides(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');

        $faker = Factory::create();
        $provider = new DatabaseFixtureProvider($faker, $pdo);

        $data = $provider->fixture('users', ['name' => 'Override']);
        self::assertSame('Override', $data['name']);
    }

    #[Test]
    public function testGetSchemaDescribesTheTableTheConnectionKnows(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');

        $schema = (new DatabaseFixtureProvider(Factory::create(), $pdo))->getSchema('users');

        self::assertSame('users', $schema->tableName);
        self::assertSame(['id', 'name'], array_keys($schema->columns));
    }

    #[Test]
    public function testGetSchemaReadsTheTableOnceAndAnswersTheSameOneAgain(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $provider = new DatabaseFixtureProvider(Factory::create(), $pdo);

        $first = $provider->getSchema('users');
        $pdo->exec('DROP TABLE users');

        self::assertSame($first, $provider->getSchema('`users`'));
    }

    #[Test]
    public function testNormalizeTableNameTakesTheQuotesOffSoBothSpellingsAreOneKey(): void
    {
        $provider = new DatabaseFixtureProvider(Factory::create(), new PDO('sqlite::memory:'));

        self::assertSame('users', $provider->normalizeTableName('`users`'));
        self::assertSame('users', $provider->normalizeTableName('"users"'));
    }

    #[Test]
    public function testGetFixtureGeneratorAnswersTheGeneratorTheRowsAreBuiltWith(): void
    {
        $provider = new DatabaseFixtureProvider(Factory::create(), new PDO('sqlite::memory:'));

        self::assertSame($provider->getFixtureGenerator(), $provider->getFixtureGenerator());
    }
}
