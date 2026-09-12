<?php

declare(strict_types=1);

namespace Tests\Integration\Sqlite;

use Faker\Factory;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\DatabaseFixtureProvider;
use SqlFixture\FixtureGenerator;
use SqlFixture\FixtureProvider;
use SqlFixture\Hydrator\ReflectionHydrator;
use SqlFixture\Platform\PlatformFactory;
use SqlFixture\Platform\Sqlite\SqliteSchemaFetcher;
use SqlFixture\Platform\Sqlite\SqliteSchemaParser;
use SqlFixture\Platform\Sqlite\SqliteTypeMapper;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\TableSchema;
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
#[CoversClass(\SqlFixture\Provider\DatabaseSchemaCache::class)]
#[UsesClass(\SqlFixture\Fixture\FixtureSet::class)]
#[UsesClass(\SqlFixture\Fixture\GenerationRun::class)]
#[UsesClass(\SqlFixture\Fixture\OverrideRows::class)]
#[UsesClass(\SqlFixture\Fixture\PlanGenerator::class)]
#[UsesClass(\SqlFixture\Fixture\PlanSchemaException::class)]
#[UsesClass(\SqlFixture\Fixture\PlanSchemaValidator::class)]
#[UsesClass(\SqlFixture\Fixture\RowSpec::class)]
#[UsesClass(\SqlFixture\Fixture\TableOverrides::class)]
#[UsesClass(\SqlFixture\Hydrator\HydrationException::class)]
#[UsesClass(\SqlFixture\Hydrator\HydratorInterface::class)]
#[UsesClass(\SqlFixture\Plan\ColumnRef::class)]
#[UsesClass(\SqlFixture\Plan\FixturePlan::class)]
#[UsesClass(\SqlFixture\Plan\PlanParser::class)]
#[UsesClass(\SqlFixture\Plan\PlanPrinter::class)]
#[UsesClass(\SqlFixture\Plan\PlanSyntaxException::class)]
#[UsesClass(\SqlFixture\Plan\Relation::class)]
#[UsesClass(\SqlFixture\Plan\RelationKind::class)]
#[UsesClass(\SqlFixture\Plan\RelationSide::class)]
#[UsesClass(\SqlFixture\Platform\MySql\MySqlSchemaFetcher::class)]
#[UsesClass(\SqlFixture\Platform\MySql\MySqlSchemaParser::class)]
#[UsesClass(\SqlFixture\Platform\MySql\MySqlTypeMapper::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\PostgreSqlSchemaFetcher::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\PostgreSqlSchemaParser::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\PostgreSqlTypeMapper::class)]
#[UsesClass(\SqlFixture\Schema\SchemaFetcherInterface::class)]
#[UsesClass(\SqlFixture\Schema\SchemaNotFoundException::class)]
#[UsesClass(\SqlFixture\Schema\SchemaParseException::class)]
#[UsesClass(\SqlFixture\Schema\SchemaParserInterface::class)]
#[UsesClass(\SqlFixture\Schema\SchemaResolverInterface::class)]
#[UsesClass(\SqlFixture\Schema\StaticSchemaResolver::class)]
#[UsesClass(\SqlFixture\Schema\TableIdentifier::class)]
#[UsesClass(\SqlFixture\TypeMapper\TypeMapperInterface::class)]
#[UsesClass(\SqlFixture\Fixture\Generation\ConnectedTables::class)]
#[UsesClass(\SqlFixture\Fixture\Generation\OverrideSpecs::class)]
#[UsesClass(\SqlFixture\Fixture\Generation\RelationCounts::class)]
#[UsesClass(\SqlFixture\Fixture\Generation\RelationProjection::class)]
#[UsesClass(\SqlFixture\Fixture\Generation\RowMaterializer::class)]
#[UsesClass(\SqlFixture\Fixture\RowGeneration::class)]
#[UsesClass(\SqlFixture\Fixture\Validation\EndpointValidator::class)]
#[UsesClass(\SqlFixture\Fixture\Validation\OverrideValidator::class)]
#[UsesClass(\SqlFixture\Hydrator\Reflection\ConstructorHydration::class)]
#[UsesClass(\SqlFixture\Hydrator\Reflection\PropertyHydration::class)]
#[UsesClass(\SqlFixture\Hydrator\Reflection\PropertyNames::class)]
#[UsesClass(\SqlFixture\Hydrator\Reflection\ValueConversion::class)]
#[UsesClass(\SqlFixture\InvalidOverrideException::class)]
#[UsesClass(\SqlFixture\Plan\Parsing\PlanStatements::class)]
#[UsesClass(\SqlFixture\Plan\Parsing\RelationCursor::class)]
#[UsesClass(\SqlFixture\Plan\Parsing\RelationReader::class)]
#[UsesClass(\SqlFixture\Plan\PlanStructureException::class)]
#[UsesClass(\SqlFixture\Plan\Printing\PlanTables::class)]
#[UsesClass(\SqlFixture\Plan\Printing\RelationGroups::class)]
#[UsesClass(\SqlFixture\Plan\Printing\StatementPrinter::class)]
#[UsesClass(\SqlFixture\Plan\Validation\PlanValidation::class)]
#[UsesClass(\SqlFixture\Plan\Validation\TableName::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Schema\ColumnParser::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Schema\CreateTableQuery::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Schema\DefaultExpression::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Schema\DefinitionIntegrity::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Schema\IdentifierQuoter::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Schema\TableDefinition::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Schema\TypeParameters::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Value\ColumnGenerator::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Value\DecimalGenerator::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Value\GeometryGenerator::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Value\IntegerGenerator::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Value\NumericGenerator::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Value\SpatialGenerator::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Value\StringGenerator::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Value\TextGenerator::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\Schema\CatalogColumn::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\Schema\CatalogDdl::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\Schema\CatalogQuery::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\Schema\CatalogSchema::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\Schema\ColumnParser::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\Schema\DefaultExpression::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\Schema\DefinitionList::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\Schema\TableSyntax::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\Schema\TypeDeclaration::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\Value\ColumnGenerator::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\Value\DecimalGenerator::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\Value\NumericGenerator::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\Value\StringGenerator::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\Value\StructuredGenerator::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\Value\TemporalGenerator::class)]
#[UsesClass(\SqlFixture\Platform\Sqlite\Schema\ColumnParser::class)]
#[UsesClass(\SqlFixture\Platform\Sqlite\Schema\CreateTableQuery::class)]
#[UsesClass(\SqlFixture\Platform\Sqlite\Schema\DefaultExpression::class)]
#[UsesClass(\SqlFixture\Platform\Sqlite\Schema\DefinitionList::class)]
#[UsesClass(\SqlFixture\Platform\Sqlite\Schema\PragmaColumn::class)]
#[UsesClass(\SqlFixture\Platform\Sqlite\Schema\PragmaSchema::class)]
#[UsesClass(\SqlFixture\Platform\Sqlite\Schema\TableSyntax::class)]
#[UsesClass(\SqlFixture\Platform\Sqlite\Schema\TypeDeclaration::class)]
#[UsesClass(\SqlFixture\Platform\Sqlite\Value\ColumnGenerator::class)]
#[UsesClass(\SqlFixture\Platform\Sqlite\Value\TypeAffinity::class)]
#[UsesClass(\SqlFixture\Provider\SqlSchemaProvider::class)]
#[UsesClass(\SqlFixture\Schema\DefinitionSegments::class)]
#[UsesClass(\SqlFixture\Schema\TypeShape::class)]
#[UsesClass(\SqlFixture\TypeMapper\ParagraphGenerator::class)]
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
