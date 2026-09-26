<?php

declare(strict_types=1);

namespace Tests\Unit\Provider;

use Faker\Factory;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\Sqlite\SqliteSchemaFetcher;
use SqlFixture\Platform\Sqlite\SqliteSchemaParser;
use SqlFixture\Platform\Sqlite\SqliteTypeMapper;
use SqlFixture\Provider\DatabaseFixtureProvider;
use SqlFixture\Provider\FixtureGenerator;
use SqlFixture\Provider\PlatformFactory;
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
#[CoversClass(\SqlFixture\Provider\DatabaseSchemaCache::class)]
#[UsesClass(\SqlFixture\Hydrator\HydrationException::class)]
#[UsesClass(\SqlFixture\Hydrator\HydratorInterface::class)]
#[UsesClass(\SqlFixture\Hydrator\ReflectionHydrator::class)]
#[UsesClass(\SqlFixture\Platform\MySql\MySqlSchemaFetcher::class)]
#[UsesClass(\SqlFixture\Platform\MySql\MySqlSchemaParser::class)]
#[UsesClass(\SqlFixture\Platform\MySql\MySqlTypeMapper::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\PostgreSqlSchemaFetcher::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\PostgreSqlSchemaParser::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\PostgreSqlTypeMapper::class)]
#[UsesClass(\SqlFixture\Schema\SchemaFetcherInterface::class)]
#[UsesClass(\SqlFixture\Schema\SchemaParseException::class)]
#[UsesClass(\SqlFixture\Schema\SchemaParserInterface::class)]
#[UsesClass(\SqlFixture\TypeMapper\TypeMapperInterface::class)]
#[UsesClass(\SqlFixture\Fixture\RowGeneration::class)]
#[UsesClass(\SqlFixture\Fixture\Validation\OverrideValidator::class)]
#[UsesClass(\SqlFixture\Hydrator\Reflection\ConstructorHydration::class)]
#[UsesClass(\SqlFixture\Hydrator\Reflection\PropertyHydration::class)]
#[UsesClass(\SqlFixture\Hydrator\Reflection\PropertyNames::class)]
#[UsesClass(\SqlFixture\Hydrator\Reflection\ValueConversion::class)]
#[UsesClass(\SqlFixture\Fixture\Exception\InvalidOverrideException::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Schema\ColumnParser::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Schema\CreateTableQuery::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Schema\DefaultExpression::class)]
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
#[UsesClass(\SqlFixture\Platform\PostgreSql\Schema\CatalogQuery::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\Schema\CatalogSchema::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\Schema\ColumnParser::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\Schema\DefaultExpression::class)]
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
#[UsesClass(\SqlFixture\Platform\Sqlite\Schema\TypeDeclaration::class)]
#[UsesClass(\SqlFixture\Platform\Sqlite\Value\ColumnGenerator::class)]
#[UsesClass(\SqlFixture\Platform\Sqlite\Value\TypeAffinity::class)]
#[UsesClass(\SqlFixture\Schema\TypeShape::class)]
#[UsesClass(\SqlFixture\TypeMapper\ParagraphGenerator::class)]
#[UsesClass(\SqlFixture\Hydrator\Exception\ClassNotFoundException::class)]
#[UsesClass(\SqlFixture\Hydrator\Exception\MissingConstructorArgumentException::class)]
#[UsesClass(\SqlFixture\Fixture\Exception\UnknownOverrideColumnException::class)]
#[UsesClass(\SqlFixture\Fixture\Exception\NullOverrideException::class)]
#[UsesClass(\SqlFixture\Fixture\Exception\GeneratedColumnOverrideException::class)]
#[UsesClass(\SqlFixture\Schema\Exception\InvalidSqlException::class)]
#[UsesClass(\SqlFixture\Schema\Exception\ExpectedCreateTableException::class)]
#[UsesClass(\SqlFixture\Schema\Exception\MissingColumnDefinitionsException::class)]
#[UsesClass(\SqlFixture\TypeMapper\IntegerRange::class)]
#[UsesClass(\SqlFixture\TypeMapper\IntegerWidth::class)]
#[UsesClass(\SqlFixture\TypeMapper\DecimalRange::class)]
#[UsesClass(\SqlFixture\Hydrator\Reflection\ConversionTarget::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Schema\ColumnAttributes::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Schema\CreateTableStatement::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Schema\Identifier::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Schema\StringLiteral::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\Schema\CatalogExpression::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\Schema\ColumnConstraints::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\Schema\CreateTableStatement::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\Schema\Identifier::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\Schema\StringLiteral::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\Schema\TableDefinition::class)]
#[UsesClass(\SqlFixture\Platform\Sqlite\Schema\ColumnConstraints::class)]
#[UsesClass(\SqlFixture\Platform\Sqlite\Schema\CreateTableStatement::class)]
#[UsesClass(\SqlFixture\Platform\Sqlite\Schema\Identifier::class)]
#[UsesClass(\SqlFixture\Platform\Sqlite\Schema\TableDefinition::class)]
#[UsesClass(\SqlFixture\Syntax\NodeReader::class)]
#[UsesClass(\SqlFixture\Syntax\NumericLiteral::class)]
#[UsesClass(\SqlFixture\Syntax\QuotedText::class)]
#[UsesClass(\SqlFixture\Fixture\RowGenerator::class)]
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
    public function testGetFixtureGeneratorReturnsInstance(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $faker = Factory::create();
        $provider = new DatabaseFixtureProvider($faker, $pdo);

        self::assertSame(['id' => 7], $provider->getFixtureGenerator()->generate(new TableSchema('sample', ['id' => new ColumnDefinition('id', 'INT')]), ['id' => 7]));
    }

    #[Test]
    public function testFixtureWithCustomTypeMapper(): void
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
    public function testFixtureWithCustomSchemaFetcher(): void
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
    public function testSchemaCacheWorksOnSecondCall(): void
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
    public function testFixtureNormalizesQuotedTableNames(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE products (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');

        $faker = Factory::create();
        $provider = new DatabaseFixtureProvider($faker, $pdo);

        $data = $provider->fixture('products');
        self::assertArrayHasKey('name', $data);
    }

    #[Test]
    public function testFixtureWithOverrides(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');

        $faker = Factory::create();
        $provider = new DatabaseFixtureProvider($faker, $pdo);

        $data = $provider->fixture('users', ['name' => 'Override']);
        self::assertSame('Override', $data['name']);
    }

}
