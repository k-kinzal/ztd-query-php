<?php

declare(strict_types=1);

namespace Tests\Integration\Sqlite;

use Faker\Factory;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlFixture\DatabaseFixtureProvider;
use SqlFixture\FixtureProvider;
use SqlFixture\Platform\PlatformFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use SqlFixture\FixtureGenerator;
use SqlFixture\Platform\Sqlite\SqliteSchemaFetcher;
use SqlFixture\Platform\Sqlite\SqliteSchemaParser;
use SqlFixture\Platform\Sqlite\SqliteTypeMapper;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\TableSchema;
use SqlFixture\Hydrator\ReflectionHydrator;
use Tests\Fixture\SqliteUserDto;

#[CoversClass(DatabaseFixtureProvider::class)]
#[UsesClass(FixtureProvider::class)]
#[UsesClass(FixtureGenerator::class)]
#[UsesClass(PlatformFactory::class)]
#[UsesClass(SqliteSchemaFetcher::class)]
#[UsesClass(SqliteSchemaParser::class)]
#[UsesClass(SqliteTypeMapper::class)]
#[UsesClass(ColumnDefinition::class)]
#[UsesClass(TableSchema::class)]
#[UsesClass(ReflectionHydrator::class)]
final class SqliteIntegrationTest extends TestCase
{
    #[Test]
    public function fixtureProviderWithSqliteDialect(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new FixtureProvider(
            $faker,
            dialect: PlatformFactory::DRIVER_SQLITE
        );

        $fixture = $provider->fixture(
            'CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, email TEXT)'
        );
        self::assertArrayHasKey('name', $fixture);
        self::assertArrayHasKey('email', $fixture);
        self::assertIsString($fixture['name']);
    }

    #[Test]
    public function databaseFixtureProviderWithSqlite(): void
    {
        $pdo = (static function (): PDO {
            $pdo = new PDO('sqlite::memory:');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $pdo;
        })();
        $pdo->exec(<<<'SQL'
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT,
                age INTEGER,
                balance REAL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
            SQL);

        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new DatabaseFixtureProvider($faker, $pdo);

        self::assertSame(PlatformFactory::DRIVER_SQLITE, $provider->getDriver());

        $fixture = $provider->fixture('users');
        self::assertArrayNotHasKey('id', $fixture);
        self::assertArrayHasKey('name', $fixture);
        self::assertIsString($fixture['name']);
    }

    #[Test]
    public function insertAndSelectFixture(): void
    {
        $pdo = (static function (): PDO {
            $pdo = new PDO('sqlite::memory:');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $pdo;
        })();
        $pdo->exec(<<<'SQL'
            CREATE TABLE products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                price REAL NOT NULL,
                quantity INTEGER DEFAULT 0
            )
            SQL);

        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new DatabaseFixtureProvider($faker, $pdo);

        $fixture = $provider->fixture('products');

        $stmt = $pdo->prepare(
            'INSERT INTO products (name, price, quantity) VALUES (:name, :price, :quantity)'
        );
        $stmt->execute($fixture);

        $id = (int) $pdo->lastInsertId();
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        self::assertNotFalse($row);
        self::assertIsArray($row);
        self::assertSame($fixture['name'], $row['name']);
        self::assertIsFloat($fixture['price']);
        self::assertIsNumeric($row['price']);
        self::assertEqualsWithDelta($fixture['price'], (float) $row['price'], 0.001);
    }

    #[Test]
    public function fixtureWithOverrides(): void
    {
        $pdo = (static function (): PDO {
            $pdo = new PDO('sqlite::memory:');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $pdo;
        })();
        $pdo->exec(<<<'SQL'
            CREATE TABLE users (
                id INTEGER PRIMARY KEY,
                name TEXT NOT NULL,
                email TEXT NOT NULL
            )
            SQL);

        $faker = Factory::create();
        $provider = new DatabaseFixtureProvider($faker, $pdo);

        $fixture = $provider->fixture('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        self::assertSame('John Doe', $fixture['name']);
        self::assertSame('john@example.com', $fixture['email']);
    }

    #[Test]
    public function fixtureWithHydration(): void
    {
        $pdo = (static function (): PDO {
            $pdo = new PDO('sqlite::memory:');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $pdo;
        })();
        $pdo->exec(<<<'SQL'
            CREATE TABLE users (
                id INTEGER PRIMARY KEY,
                name TEXT NOT NULL,
                email TEXT NOT NULL
            )
            SQL);

        $faker = Factory::create();
        $provider = new DatabaseFixtureProvider($faker, $pdo);

        $user = $provider->fixture(
            'users',
            ['id' => 1],
            SqliteUserDto::class
        );

        self::assertInstanceOf(SqliteUserDto::class, $user);
        self::assertSame(1, $user->id);
        self::assertNotEmpty($user->name);
        self::assertNotEmpty($user->email);
    }

    #[Test]
    public function fixtureProviderDialectOverride(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);

        $provider = new FixtureProvider($faker);

        $fixture = $provider->fixture(
            'CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)',
            [],
            null,
            PlatformFactory::DRIVER_SQLITE
        );
        self::assertArrayHasKey('name', $fixture);
    }

    #[Test]
    public function allSqliteTypesFixture(): void
    {
        $pdo = (static function (): PDO {
            $pdo = new PDO('sqlite::memory:');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $pdo;
        })();
        $pdo->exec(<<<'SQL'
            CREATE TABLE all_types (
                col_integer INTEGER NOT NULL,
                col_int INT NOT NULL,
                col_tinyint TINYINT NOT NULL,
                col_smallint SMALLINT NOT NULL,
                col_mediumint MEDIUMINT NOT NULL,
                col_bigint BIGINT NOT NULL,
                col_text TEXT NOT NULL,
                col_varchar VARCHAR(100) NOT NULL,
                col_char CHAR(10) NOT NULL,
                col_real REAL NOT NULL,
                col_float FLOAT NOT NULL,
                col_double DOUBLE NOT NULL,
                col_decimal DECIMAL(10, 2) NOT NULL,
                col_blob BLOB NOT NULL,
                col_boolean BOOLEAN NOT NULL,
                col_date DATE NOT NULL,
                col_time TIME NOT NULL,
                col_datetime DATETIME NOT NULL
            )
            SQL);

        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new DatabaseFixtureProvider($faker, $pdo);

        $fixture = $provider->fixture('all_types');

        self::assertIsInt($fixture['col_integer']);
        self::assertIsInt($fixture['col_int']);
        self::assertIsInt($fixture['col_tinyint']);
        self::assertIsInt($fixture['col_smallint']);
        self::assertIsInt($fixture['col_mediumint']);
        self::assertIsInt($fixture['col_bigint']);
        self::assertIsString($fixture['col_text']);
        self::assertIsString($fixture['col_varchar']);
        self::assertIsString($fixture['col_char']);
        self::assertIsFloat($fixture['col_real']);
        self::assertIsFloat($fixture['col_float']);
        self::assertIsFloat($fixture['col_double']);
        self::assertIsFloat($fixture['col_decimal']);
        self::assertIsString($fixture['col_blob']);
        self::assertContains($fixture['col_boolean'], [0, 1]);
        $colDate = $fixture['col_date'];
        self::assertIsString($colDate);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $colDate);
        $colTime = $fixture['col_time'];
        self::assertIsString($colTime);
        self::assertMatchesRegularExpression('/^\d{2}:\d{2}:\d{2}$/', $colTime);
        $colDatetime = $fixture['col_datetime'];
        self::assertIsString($colDatetime);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $colDatetime);
    }
}
