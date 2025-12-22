<?php

declare(strict_types=1);

namespace Tests\Unit;

use Faker\Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlFixture\FixtureProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use SqlFixture\FixtureGenerator;
use SqlFixture\Platform\PlatformFactory;
use SqlFixture\Platform\MySql\MySqlSchemaParser;
use SqlFixture\Platform\MySql\MySqlTypeMapper;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\TableSchema;
use SqlFixture\Hydrator\ReflectionHydrator;
use SqlFixture\Platform\PostgreSql\PostgreSqlTypeMapper;
use SqlFixture\Platform\Sqlite\SqliteSchemaParser;
use SqlFixture\Platform\Sqlite\SqliteTypeMapper;
use Tests\Fixture\TestableFixtureProvider;
use Tests\Fixture\UserDto;

#[CoversClass(FixtureProvider::class)]
#[UsesClass(FixtureGenerator::class)]
#[UsesClass(PlatformFactory::class)]
#[UsesClass(MySqlSchemaParser::class)]
#[UsesClass(MySqlTypeMapper::class)]
#[UsesClass(ColumnDefinition::class)]
#[UsesClass(TableSchema::class)]
#[UsesClass(ReflectionHydrator::class)]
#[UsesClass(PostgreSqlTypeMapper::class)]
#[UsesClass(SqliteSchemaParser::class)]
#[UsesClass(SqliteTypeMapper::class)]
final class FixtureProviderTest extends TestCase
{
    #[Test]
    public function fixtureReturnsArray(): void
    {
        $data = (static function (): FixtureProvider {
            $faker = Factory::create();
            $faker->seed(12345);
            return new FixtureProvider($faker);
        })()->fixture(
            'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255) NOT NULL)',
        );

        self::assertArrayHasKey('name', $data);
        self::assertIsString($data['name']);
    }

    #[Test]
    public function fixtureWithOverrides(): void
    {
        $data = (static function (): FixtureProvider {
            $faker = Factory::create();
            $faker->seed(12345);
            return new FixtureProvider($faker);
        })()->fixture(
            'CREATE TABLE users (id INT, name VARCHAR(255))',
            ['name' => 'Overridden'],
        );

        self::assertSame('Overridden', $data['name']);
    }

    #[Test]
    public function fixtureHydratesClass(): void
    {
        $user = (static function (): FixtureProvider {
            $faker = Factory::create();
            $faker->seed(12345);
            return new FixtureProvider($faker);
        })()->fixture(
            'CREATE TABLE users (id INT, name VARCHAR(255))',
            ['id' => 1, 'name' => 'Test User'],
            UserDto::class,
        );

        self::assertInstanceOf(UserDto::class, $user);
        self::assertSame(1, $user->id);
        self::assertSame('Test User', $user->name);
    }

    #[Test]
    public function fixtureSkipsAutoIncrement(): void
    {
        $data = (static function (): FixtureProvider {
            $faker = Factory::create();
            $faker->seed(12345);
            return new FixtureProvider($faker);
        })()->fixture(
            'CREATE TABLE users (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(255))',
        );

        self::assertArrayNotHasKey('id', $data);
        self::assertArrayHasKey('name', $data);
    }

    #[Test]
    public function fixtureCanOverrideAutoIncrement(): void
    {
        $data = (static function (): FixtureProvider {
            $faker = Factory::create();
            $faker->seed(12345);
            return new FixtureProvider($faker);
        })()->fixture(
            'CREATE TABLE users (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(255))',
            ['id' => 42],
        );

        self::assertSame(42, $data['id']);
    }

    #[Test]
    public function fixtureWithAllNumericTypes(): void
    {
        $data = (static function (): FixtureProvider {
            $faker = Factory::create();
            $faker->seed(12345);
            return new FixtureProvider($faker);
        })()->fixture(<<<'SQL'
            CREATE TABLE numbers (
                col_tinyint TINYINT NOT NULL,
                col_smallint SMALLINT NOT NULL,
                col_mediumint MEDIUMINT NOT NULL,
                col_int INT NOT NULL,
                col_bigint BIGINT NOT NULL,
                col_float FLOAT NOT NULL,
                col_double DOUBLE NOT NULL,
                col_decimal DECIMAL(10,2) NOT NULL
            )
            SQL);

        self::assertIsInt($data['col_tinyint']);
        self::assertIsInt($data['col_smallint']);
        self::assertIsInt($data['col_mediumint']);
        self::assertIsInt($data['col_int']);
        self::assertIsInt($data['col_bigint']);
        self::assertIsFloat($data['col_float']);
        self::assertIsFloat($data['col_double']);
        self::assertIsFloat($data['col_decimal']);
    }

    #[Test]
    public function fixtureWithStringTypes(): void
    {
        $data = (static function (): FixtureProvider {
            $faker = Factory::create();
            $faker->seed(12345);
            return new FixtureProvider($faker);
        })()->fixture(<<<'SQL'
            CREATE TABLE strings (
                col_char CHAR(10) NOT NULL,
                col_varchar VARCHAR(100) NOT NULL,
                col_text TEXT NOT NULL,
                col_mediumtext MEDIUMTEXT NOT NULL
            )
            SQL);

        self::assertIsString($data['col_char']);
        self::assertSame(10, strlen($data['col_char']));
        self::assertIsString($data['col_varchar']);
        self::assertLessThanOrEqual(100, strlen($data['col_varchar']));
        self::assertIsString($data['col_text']);
        self::assertIsString($data['col_mediumtext']);
    }

    #[Test]
    public function fixtureWithDateTypes(): void
    {
        $data = (static function (): FixtureProvider {
            $faker = Factory::create();
            $faker->seed(12345);
            return new FixtureProvider($faker);
        })()->fixture(<<<'SQL'
            CREATE TABLE dates (
                col_date DATE NOT NULL,
                col_time TIME NOT NULL,
                col_datetime DATETIME NOT NULL,
                col_timestamp TIMESTAMP NOT NULL,
                col_year YEAR NOT NULL
            )
            SQL);

        self::assertIsString($data['col_date']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $data['col_date']);
        self::assertIsString($data['col_time']);
        self::assertMatchesRegularExpression('/^\d{2}:\d{2}:\d{2}$/', $data['col_time']);
        self::assertIsString($data['col_datetime']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $data['col_datetime']);
        self::assertIsInt($data['col_year']);
    }

    #[Test]
    public function fixtureWithEnum(): void
    {
        $data = (static function (): FixtureProvider {
            $faker = Factory::create();
            $faker->seed(12345);
            return new FixtureProvider($faker);
        })()->fixture(<<<'SQL'
            CREATE TABLE statuses (
                status ENUM('active','inactive','pending') NOT NULL
            )
            SQL);

        self::assertContains($data['status'], ['active', 'inactive', 'pending']);
    }

    #[Test]
    public function fixtureWithSet(): void
    {
        $data = (static function (): FixtureProvider {
            $faker = Factory::create();
            $faker->seed(12345);
            return new FixtureProvider($faker);
        })()->fixture(<<<'SQL'
            CREATE TABLE permissions (
                perms SET('read','write','delete') NOT NULL
            )
            SQL);

        self::assertIsString($data['perms']);
        $parts = explode(',', $data['perms']);
        array_walk($parts, static function (string $part): void {
            self::assertContains($part, ['read', 'write', 'delete']);
        });
    }

    #[Test]
    public function fixtureWithJson(): void
    {
        $data = (static function (): FixtureProvider {
            $faker = Factory::create();
            $faker->seed(12345);
            return new FixtureProvider($faker);
        })()->fixture('CREATE TABLE jsons (data JSON NOT NULL)');

        self::assertIsString($data['data']);
        $decoded = json_decode($data['data'], true);
        self::assertIsArray($decoded);
    }

    #[Test]
    public function fixtureWithSpatialTypes(): void
    {
        $data = (static function (): FixtureProvider {
            $faker = Factory::create();
            $faker->seed(12345);
            return new FixtureProvider($faker);
        })()->fixture(<<<'SQL'
            CREATE TABLE geo (
                col_point POINT NOT NULL,
                col_linestring LINESTRING NOT NULL,
                col_polygon POLYGON NOT NULL
            )
            SQL);

        self::assertIsString($data['col_point']);
        self::assertStringStartsWith('POINT(', $data['col_point']);
        self::assertIsString($data['col_linestring']);
        self::assertStringStartsWith('LINESTRING(', $data['col_linestring']);
        self::assertIsString($data['col_polygon']);
        self::assertStringStartsWith('POLYGON((', $data['col_polygon']);
    }

    #[Test]
    public function fixtureResultIsReproducibleWithSeed(): void
    {
        $faker1 = Factory::create();
        $faker1->seed(99999);
        $data1 = (new FixtureProvider($faker1))->fixture('CREATE TABLE test (name VARCHAR(255))');
        $faker2 = Factory::create();
        $faker2->seed(99999);
        $data2 = (new FixtureProvider($faker2))->fixture('CREATE TABLE test (name VARCHAR(255))');

        self::assertSame($data1['name'], $data2['name']);
    }

    #[Test]
    public function fixtureCachesSchema(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new FixtureProvider($faker);
        $sql = 'CREATE TABLE cache_test (id INT, name VARCHAR(255))';

        $data1 = $provider->fixture($sql, ['name' => 'First']);
        $data2 = $provider->fixture($sql, ['name' => 'Second']);

        self::assertSame('First', $data1['name']);
        self::assertSame('Second', $data2['name']);
    }

    #[Test]
    public function getFixtureGenerator(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $generator = (new FixtureProvider($faker))->getFixtureGenerator();
        self::assertInstanceOf(\SqlFixture\FixtureGenerator::class, $generator);
    }

    #[Test]
    public function getDialectDefaultsToMysql(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        self::assertSame('mysql', (new FixtureProvider($faker))->getDialect());
    }

    #[Test]
    public function fixtureWithNullableColumns(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new FixtureProvider($faker);
        $sql = 'CREATE TABLE test (id INT NOT NULL, name VARCHAR(255) DEFAULT NULL, notes TEXT)';

        (static function () use ($provider, $sql): void {
            for ($i = 0; $i < 20; $i++) {
                $data = $provider->fixture($sql);
                self::assertArrayHasKey('id', $data);
                self::assertIsInt($data['id']);
            }
        })();
    }

    #[Test]
    public function fixtureWithBinaryColumns(): void
    {
        $data = (static function (): FixtureProvider {
            $faker = Factory::create();
            $faker->seed(12345);
            return new FixtureProvider($faker);
        })()->fixture(<<<'SQL'
            CREATE TABLE bins (
                col_binary BINARY(16) NOT NULL,
                col_varbinary VARBINARY(100) NOT NULL,
                col_blob BLOB NOT NULL
            )
            SQL);

        self::assertIsString($data['col_binary']);
        self::assertSame(16, strlen($data['col_binary']));
        self::assertIsString($data['col_varbinary']);
        self::assertLessThanOrEqual(100, strlen($data['col_varbinary']));
        self::assertIsString($data['col_blob']);
    }

    #[Test]
    public function fixtureWithBooleanType(): void
    {
        $data = (static function (): FixtureProvider {
            $faker = Factory::create();
            $faker->seed(12345);
            return new FixtureProvider($faker);
        })()->fixture('CREATE TABLE test (active BOOLEAN NOT NULL)');
        self::assertIsBool($data['active']);
    }

    #[Test]
    public function fixtureWithBitType(): void
    {
        $data = (static function (): FixtureProvider {
            $faker = Factory::create();
            $faker->seed(12345);
            return new FixtureProvider($faker);
        })()->fixture('CREATE TABLE test (flags BIT(8) NOT NULL)');
        self::assertIsInt($data['flags']);
        self::assertGreaterThanOrEqual(0, $data['flags']);
        self::assertLessThanOrEqual(255, $data['flags']);
    }

    #[Test]
    public function fixtureWithGeneratedColumnSkipped(): void
    {
        $data = (static function (): FixtureProvider {
            $faker = Factory::create();
            $faker->seed(12345);
            return new FixtureProvider($faker);
        })()->fixture(<<<'SQL'
            CREATE TABLE test (
                a INT,
                b INT,
                c INT GENERATED ALWAYS AS (a + b) STORED
            )
            SQL);

        self::assertArrayHasKey('a', $data);
        self::assertArrayHasKey('b', $data);
        self::assertArrayNotHasKey('c', $data);
    }

    #[Test]
    public function fixtureWithUnsignedTypes(): void
    {
        $data = (static function (): FixtureProvider {
            $faker = Factory::create();
            $faker->seed(12345);
            return new FixtureProvider($faker);
        })()->fixture(<<<'SQL'
            CREATE TABLE test (
                col_uint INT UNSIGNED NOT NULL,
                col_utinyint TINYINT UNSIGNED NOT NULL
            )
            SQL);

        self::assertIsInt($data['col_uint']);
        self::assertGreaterThanOrEqual(0, $data['col_uint']);
        self::assertIsInt($data['col_utinyint']);
        self::assertGreaterThanOrEqual(0, $data['col_utinyint']);
        self::assertLessThanOrEqual(255, $data['col_utinyint']);
    }

    #[Test]
    public function fixtureWithDialectOverride(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new FixtureProvider($faker, dialect: 'mysql');

        $data = $provider->fixture(
            'CREATE TABLE test (id INTEGER PRIMARY KEY, name TEXT)',
            [],
            null,
            'sqlite',
        );

        self::assertArrayHasKey('name', $data);
    }

    #[Test]
    public function fixtureWithSqliteDialect(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new FixtureProvider($faker, dialect: 'sqlite');

        self::assertSame('sqlite', $provider->getDialect());

        $data = $provider->fixture(
            'CREATE TABLE test (id INTEGER PRIMARY KEY, name TEXT)',
        );

        self::assertArrayHasKey('name', $data);
    }

    #[Test]
    public function fixtureWithCustomTypeMapper(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $typeMapper = new MySqlTypeMapper();
        $provider = new FixtureProvider($faker, typeMapper: $typeMapper);

        $data = $provider->fixture('CREATE TABLE test (id INT, name VARCHAR(255))');

        self::assertArrayHasKey('id', $data);
    }

    #[Test]
    public function fixtureWithCustomSchemaParser(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $parser = new MySqlSchemaParser();
        $provider = new FixtureProvider($faker, schemaParser: $parser);

        $data = $provider->fixture('CREATE TABLE test (id INT, name VARCHAR(255))');

        self::assertArrayHasKey('id', $data);
    }

    #[Test]
    public function schemaCacheUsesDialectInKey(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new FixtureProvider($faker, dialect: 'mysql');

        $sql = 'CREATE TABLE test (id INTEGER PRIMARY KEY, name TEXT NOT NULL)';
        $data1 = $provider->fixture($sql, [], null, 'sqlite');
        $data2 = $provider->fixture($sql, [], null, 'sqlite');

        self::assertArrayHasKey('name', $data1);
        self::assertArrayHasKey('name', $data2);
    }

    #[Test]
    public function schemaCacheDistinguishesDifferentDialects(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new FixtureProvider($faker, dialect: 'mysql');

        $sql = 'CREATE TABLE test (id INTEGER NOT NULL, name TEXT NOT NULL)';

        $faker->seed(12345);
        $dataMysql = $provider->fixture($sql);

        $faker->seed(12345);
        $dataSqlite = $provider->fixture($sql, [], null, 'sqlite');

        self::assertArrayHasKey('name', $dataMysql);
        self::assertArrayHasKey('name', $dataSqlite);
    }

    #[Test]
    public function defaultDialectIsUsedWhenNullDialectPassed(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new FixtureProvider($faker, dialect: 'sqlite');

        $data = $provider->fixture(
            'CREATE TABLE test (id INTEGER PRIMARY KEY, name TEXT NOT NULL)',
            [],
            null,
            null,
        );

        self::assertArrayHasKey('name', $data);
        self::assertSame('sqlite', $provider->getDialect());
    }

    #[Test]
    public function fixtureSameDialectUsesInternalParser(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new FixtureProvider($faker, dialect: 'mysql');

        $data = $provider->fixture(
            'CREATE TABLE test (id INT, name VARCHAR(255))',
            [],
            null,
            'mysql',
        );

        self::assertArrayHasKey('name', $data);
    }

    #[Test]
    public function cacheDistinguishesDifferentSqlSameDialect(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new FixtureProvider($faker, dialect: 'mysql');

        $data1 = $provider->fixture(
            'CREATE TABLE test1 (id INT NOT NULL, name VARCHAR(255) NOT NULL)',
        );
        $data2 = $provider->fixture(
            'CREATE TABLE test2 (age INT NOT NULL, email VARCHAR(100) NOT NULL)',
        );

        self::assertArrayHasKey('name', $data1);
        self::assertArrayNotHasKey('email', $data1);
        self::assertArrayHasKey('email', $data2);
        self::assertArrayNotHasKey('name', $data2);
    }

    #[Test]
    public function cacheKeyIncludesDialect(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new FixtureProvider($faker, dialect: 'mysql');

        $sql = 'CREATE TABLE test (id INTEGER NOT NULL, name TEXT NOT NULL)';

        $dataMysql = $provider->fixture($sql);
        $dataSqlite = $provider->fixture($sql, [], null, 'sqlite');

        self::assertArrayHasKey('id', $dataMysql);
        self::assertArrayHasKey('id', $dataSqlite);
        self::assertArrayHasKey('name', $dataMysql);
        self::assertArrayHasKey('name', $dataSqlite);
    }

    #[Test]
    public function dialectOverrideUsesOverrideNotDefault(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new FixtureProvider($faker, dialect: 'mysql');

        $sql = 'CREATE TABLE test (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)';

        $data = $provider->fixture($sql, [], null, 'sqlite');

        self::assertArrayNotHasKey('id', $data);
        self::assertArrayHasKey('name', $data);
    }

    #[Test]
    public function cachedSchemaDistinguishesDialects(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new FixtureProvider($faker, dialect: 'mysql');

        $sql = 'CREATE TABLE test (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)';

        $provider->fixture($sql, ['name' => 'first'], null, 'sqlite');

        $data2 = $provider->fixture($sql, ['name' => 'second'], null, 'sqlite');

        self::assertArrayNotHasKey('id', $data2);
        self::assertSame('second', $data2['name']);
    }

    #[Test]
    public function cacheSeparatesDefaultAndOverrideDialect(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new FixtureProvider($faker, dialect: 'sqlite');

        $sql = 'CREATE TABLE test (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)';

        $dataSqlite = $provider->fixture($sql, ['name' => 'sqlite']);
        self::assertArrayNotHasKey('id', $dataSqlite);

        $dataMysql = $provider->fixture($sql, ['name' => 'mysql'], null, 'mysql');
        self::assertArrayHasKey('id', $dataMysql);
    }

    #[Test]
    public function customTypeMapperIsPreserved(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new FixtureProvider($faker, typeMapper: new PostgreSqlTypeMapper());

        $data = $provider->fixture('CREATE TABLE test (val YEAR NOT NULL)');

        self::assertIsString($data['val']);
    }

    #[Test]
    public function customSchemaParserIsPreserved(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $parser = new SqliteSchemaParser();
        $provider = new FixtureProvider($faker, schemaParser: $parser);

        $data = $provider->fixture('CREATE TABLE test (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');

        self::assertArrayNotHasKey('id', $data);
        self::assertArrayHasKey('name', $data);
    }

    #[Test]
    public function getSchemaIsAccessibleFromSubclass(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new TestableFixtureProvider($faker);

        $schema = $provider->exposeGetSchema('CREATE TABLE test (id INT NOT NULL, name VARCHAR(255) NOT NULL)');

        self::assertSame('test', $schema->tableName);
        self::assertCount(2, $schema->columns);
    }
}
