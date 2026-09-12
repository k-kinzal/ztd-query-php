<?php

declare(strict_types=1);

namespace Tests\Unit;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\FixtureGenerator;
use SqlFixture\FixtureProvider;
use SqlFixture\Hydrator\ReflectionHydrator;
use SqlFixture\Platform\MySql\MySqlSchemaParser;
use SqlFixture\Platform\MySql\MySqlTypeMapper;
use SqlFixture\Platform\PlatformFactory;
use SqlFixture\Platform\PostgreSql\PostgreSqlTypeMapper;
use SqlFixture\Platform\Sqlite\SqliteSchemaParser;
use SqlFixture\Platform\Sqlite\SqliteTypeMapper;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\StaticSchemaResolver;
use SqlFixture\Schema\TableSchema;
use Tests\Fixture\TestableFixtureProvider;
use Tests\Fixture\UserDto;

#[CoversClass(FixtureProvider::class)]
#[UsesClass(FixtureGenerator::class)]
#[UsesClass(PlatformFactory::class)]
#[UsesClass(MySqlSchemaParser::class)]
#[UsesClass(MySqlTypeMapper::class)]
#[UsesClass(ColumnDefinition::class)]
#[UsesClass(TableSchema::class)]
#[UsesClass(StaticSchemaResolver::class)]
#[UsesClass(ReflectionHydrator::class)]
#[UsesClass(PostgreSqlTypeMapper::class)]
#[UsesClass(SqliteSchemaParser::class)]
#[UsesClass(SqliteTypeMapper::class)]
#[CoversClass(\SqlFixture\Provider\SqlSchemaProvider::class)]
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
#[UsesClass(\SqlFixture\Platform\PostgreSql\PostgreSqlSchemaFetcher::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\PostgreSqlSchemaParser::class)]
#[UsesClass(\SqlFixture\Platform\Sqlite\SqliteSchemaFetcher::class)]
#[UsesClass(\SqlFixture\Schema\SchemaFetcherInterface::class)]
#[UsesClass(\SqlFixture\Schema\SchemaNotFoundException::class)]
#[UsesClass(\SqlFixture\Schema\SchemaParseException::class)]
#[UsesClass(\SqlFixture\Schema\SchemaParserInterface::class)]
#[UsesClass(\SqlFixture\Schema\SchemaResolverInterface::class)]
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
#[UsesClass(\SqlFixture\Schema\DefinitionSegments::class)]
#[UsesClass(\SqlFixture\Schema\TypeShape::class)]
#[UsesClass(\SqlFixture\TypeMapper\ParagraphGenerator::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Schema\TableDefinitionInput::class)]
final class FixtureProviderTest extends TestCase
{
    #[Test]
    public function testFixtureReturnsArray(): void
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
    public function testFixtureWithOverrides(): void
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
    public function testFixtureHydratesClass(): void
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

        self::assertSame(1, $user->id);
        self::assertSame('Test User', $user->name);
    }

    #[Test]
    public function testFixtureSkipsAutoIncrement(): void
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
    public function testFixtureCanOverrideAutoIncrement(): void
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
    public function testFixtureWithAllNumericTypes(): void
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
    public function testFixtureWithStringTypes(): void
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
    public function testFixtureWithDateTypes(): void
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
    public function testFixtureWithEnum(): void
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
    public function testFixtureWithSet(): void
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
    public function testFixtureWithJson(): void
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
    public function testFixtureWithSpatialTypes(): void
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
    public function testFixtureResultIsReproducibleWithSeed(): void
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
    public function testFixtureCachesSchema(): void
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
    public function testGetFixtureGenerator(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $generator = (new FixtureProvider($faker))->getFixtureGenerator();
        self::assertSame(['id' => 7], $generator->generate(new TableSchema('sample', ['id' => new ColumnDefinition('id', 'INT')]), ['id' => 7]));
    }

    #[Test]
    public function testGetDialectDefaultsToMysql(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        self::assertSame('mysql', (new FixtureProvider($faker))->getDialect());
    }

    #[Test]
    public function testFixtureWithNullableColumns(): void
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
    public function testFixtureWithBinaryColumns(): void
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
    public function testFixtureWithBooleanType(): void
    {
        $data = (static function (): FixtureProvider {
            $faker = Factory::create();
            $faker->seed(12345);
            return new FixtureProvider($faker);
        })()->fixture('CREATE TABLE test (active BOOLEAN NOT NULL)');
        self::assertIsBool($data['active']);
    }

    #[Test]
    public function testFixtureWithBitType(): void
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
    public function testFixtureWithGeneratedColumnSkipped(): void
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
    public function testFixtureWithUnsignedTypes(): void
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
    public function testFixtureWithDialectOverride(): void
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
    public function testFixtureWithSqliteDialect(): void
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
    public function testFixtureWithCustomTypeMapper(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $typeMapper = new MySqlTypeMapper();
        $provider = new FixtureProvider($faker, typeMapper: $typeMapper);

        $data = $provider->fixture('CREATE TABLE test (id INT, name VARCHAR(255))');

        self::assertArrayHasKey('id', $data);
    }

    #[Test]
    public function testFixtureWithCustomSchemaParser(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $parser = new MySqlSchemaParser();
        $provider = new FixtureProvider($faker, schemaParser: $parser);

        $data = $provider->fixture('CREATE TABLE test (id INT, name VARCHAR(255))');

        self::assertArrayHasKey('id', $data);
    }

    #[Test]
    public function testSchemaCacheUsesDialectInKey(): void
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
    public function testSchemaCacheDistinguishesDifferentDialects(): void
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
    public function testDefaultDialectIsUsedWhenNullDialectPassed(): void
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
    public function testFixtureSameDialectUsesInternalParser(): void
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
    public function testCacheDistinguishesDifferentSqlSameDialect(): void
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
    public function testCacheKeyIncludesDialect(): void
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
    public function testDialectOverrideUsesOverrideNotDefault(): void
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
    public function testCachedSchemaDistinguishesDialects(): void
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
    public function testCacheSeparatesDefaultAndOverrideDialect(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new FixtureProvider($faker, dialect: 'sqlite');

        $sql = 'CREATE TABLE test (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50) NOT NULL)';

        $dataSqlite = $provider->fixture($sql);
        self::assertArrayHasKey('id', $dataSqlite);

        $dataMysql = $provider->fixture($sql, [], null, 'mysql');
        self::assertArrayNotHasKey('id', $dataMysql);
    }

    #[Test]
    public function testCustomTypeMapperIsPreserved(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new FixtureProvider($faker, typeMapper: new PostgreSqlTypeMapper());

        $data = $provider->fixture('CREATE TABLE test (val YEAR NOT NULL)');

        self::assertIsString($data['val']);
    }

    #[Test]
    public function testCustomSchemaParserIsPreserved(): void
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
    public function testGetSchemaIsAccessibleFromSubclass(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new TestableFixtureProvider($faker);

        $schema = $provider->exposeGetSchema('CREATE TABLE test (id INT NOT NULL, name VARCHAR(255) NOT NULL)');

        self::assertSame('test', $schema->tableName);
        self::assertCount(2, $schema->columns);
    }

    #[Test]
    public function testRegisterSchemaARegisteredSchemaBecomesAvailableToFixtures(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new FixtureProvider($faker);
        $provider->registerSchema('CREATE TABLE orders (id INT AUTO_INCREMENT PRIMARY KEY, status VARCHAR(20) NOT NULL)');

        self::assertTrue($provider->getSchemaResolver()->has('orders'));
        self::assertSame('orders', $provider->getSchemaResolver()->resolve('orders')->tableName);
    }

    #[Test]
    public function testGetSchemaResolverGeneratingAFixtureAlsoMakesItAvailableToFixtures(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new FixtureProvider($faker);
        $provider->fixture('CREATE TABLE orders (id INT AUTO_INCREMENT PRIMARY KEY, status VARCHAR(20) NOT NULL)');

        self::assertTrue($provider->getSchemaResolver()->has('orders'));
    }
    public function testFixturesGeneratesLinkedRowsFromRegisteredSchemas(): void
    {
        $provider = new FixtureProvider(Factory::create());
        $provider->registerSchema('CREATE TABLE users (id INT PRIMARY KEY)');
        $provider->registerSchema('CREATE TABLE posts (user_id INT)');
        $fixtures = $provider->fixtures('users.id < posts.user_id', ['users' => ['id' => 9], 'posts' => 2]);
        self::assertSame([['user_id' => 9], ['user_id' => 9]], $fixtures->rows('posts'));
    }
}
