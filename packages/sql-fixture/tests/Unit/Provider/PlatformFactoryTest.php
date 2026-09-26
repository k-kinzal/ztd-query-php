<?php

declare(strict_types=1);

namespace Tests\Unit\Provider;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\MySqlSchemaFetcher;
use SqlFixture\Platform\MySql\MySqlSchemaParser;
use SqlFixture\Platform\MySql\MySqlTypeMapper;
use SqlFixture\Platform\PostgreSql\PostgreSqlSchemaFetcher;
use SqlFixture\Platform\PostgreSql\PostgreSqlSchemaParser;
use SqlFixture\Platform\PostgreSql\PostgreSqlTypeMapper;
use SqlFixture\Platform\Sqlite\SqliteSchemaFetcher;
use SqlFixture\Platform\Sqlite\SqliteSchemaParser;
use SqlFixture\Platform\Sqlite\SqliteTypeMapper;
use SqlFixture\Provider\PlatformFactory;

#[CoversClass(PlatformFactory::class)]
#[UsesClass(SqliteSchemaFetcher::class)]
#[UsesClass(PostgreSqlSchemaFetcher::class)]
#[UsesClass(MySqlSchemaFetcher::class)]
#[UsesClass(MySqlSchemaParser::class)]
#[UsesClass(MySqlTypeMapper::class)]
#[UsesClass(PostgreSqlSchemaParser::class)]
#[UsesClass(PostgreSqlTypeMapper::class)]
#[UsesClass(SqliteSchemaParser::class)]
#[UsesClass(SqliteTypeMapper::class)]
#[UsesClass(\SqlFixture\Schema\ColumnDefinition::class)]
#[UsesClass(\SqlFixture\Schema\SchemaFetcherInterface::class)]
#[UsesClass(\SqlFixture\Schema\SchemaParseException::class)]
#[UsesClass(\SqlFixture\Schema\SchemaParserInterface::class)]
#[UsesClass(\SqlFixture\Schema\TableSchema::class)]
#[UsesClass(\SqlFixture\TypeMapper\TypeMapperInterface::class)]
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
#[UsesClass(\SqlFixture\Schema\Exception\InvalidSqlException::class)]
#[UsesClass(\SqlFixture\Schema\Exception\ExpectedCreateTableException::class)]
#[UsesClass(\SqlFixture\Schema\Exception\MissingColumnDefinitionsException::class)]
#[UsesClass(\SqlFixture\TypeMapper\IntegerRange::class)]
#[UsesClass(\SqlFixture\TypeMapper\IntegerWidth::class)]
#[UsesClass(\SqlFixture\TypeMapper\DecimalRange::class)]
#[UsesClass(\SqlFixture\Version\ServerVersion::class)]
#[UsesClass(\SqlFixture\Version\Releases::class)]
#[UsesClass(\SqlFixture\Version\ReleaseNumber::class)]
#[UsesClass(\SqlFixture\Version\UnsupportedVersionException::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class PlatformFactoryTest extends TestCase
{
    #[Test]
    public function testCreateSchemaParserForMysql(): void
    {
        $parser = PlatformFactory::createSchemaParser(PlatformFactory::DRIVER_MYSQL);

        self::assertInstanceOf(MySqlSchemaParser::class, $parser);
    }

    #[Test]
    public function testCreateSchemaParserForSqlite(): void
    {
        $parser = PlatformFactory::createSchemaParser(PlatformFactory::DRIVER_SQLITE);

        self::assertInstanceOf(SqliteSchemaParser::class, $parser);
    }

    #[Test]
    public function testCreateSchemaParserForPgsql(): void
    {
        $parser = PlatformFactory::createSchemaParser(PlatformFactory::DRIVER_PGSQL);

        self::assertInstanceOf(PostgreSqlSchemaParser::class, $parser);
    }

    #[Test]
    public function testCreateTypeMapperForMysql(): void
    {
        $mapper = PlatformFactory::createTypeMapper(PlatformFactory::DRIVER_MYSQL);

        self::assertInstanceOf(MySqlTypeMapper::class, $mapper);
    }

    #[Test]
    public function testCreateTypeMapperForSqlite(): void
    {
        $mapper = PlatformFactory::createTypeMapper(PlatformFactory::DRIVER_SQLITE);

        self::assertInstanceOf(SqliteTypeMapper::class, $mapper);
    }

    #[Test]
    public function testCreateTypeMapperForPgsql(): void
    {
        $mapper = PlatformFactory::createTypeMapper(PlatformFactory::DRIVER_PGSQL);

        self::assertInstanceOf(PostgreSqlTypeMapper::class, $mapper);
    }

    #[Test]
    public function testCreateSchemaFetcherForMysql(): void
    {
        $fetcher = PlatformFactory::createSchemaFetcher(PlatformFactory::DRIVER_MYSQL);

        self::assertInstanceOf(MySqlSchemaFetcher::class, $fetcher);
    }

    #[Test]
    public function testCreateSchemaFetcherForSqlite(): void
    {
        $fetcher = PlatformFactory::createSchemaFetcher(PlatformFactory::DRIVER_SQLITE);

        self::assertInstanceOf(SqliteSchemaFetcher::class, $fetcher);
    }

    #[Test]
    public function testCreateSchemaFetcherForPgsql(): void
    {
        $fetcher = PlatformFactory::createSchemaFetcher(PlatformFactory::DRIVER_PGSQL);

        self::assertInstanceOf(PostgreSqlSchemaFetcher::class, $fetcher);
    }

    #[Test]
    public function testDetectDriverForSqlite(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $driver = PlatformFactory::detectDriver($pdo);

        self::assertSame(PlatformFactory::DRIVER_SQLITE, $driver);
    }

    #[Test]
    public function testGetSupportedDrivers(): void
    {
        $drivers = PlatformFactory::getSupportedDrivers();

        self::assertContains(PlatformFactory::DRIVER_MYSQL, $drivers);
        self::assertContains(PlatformFactory::DRIVER_SQLITE, $drivers);
        self::assertContains(PlatformFactory::DRIVER_PGSQL, $drivers);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerDatabaseDrivers')]
    public function testDetectDriverReadsTheNativeConnection(string $prefix, string $expected): void
    {
        $pdo = new PDO(
            (string) getenv($prefix . '_DSN'),
            (string) getenv($prefix . '_USER'),
            (string) getenv($prefix . '_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false],
        );
        self::assertSame($expected, PlatformFactory::detectDriver($pdo));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerDatabaseDrivers(): iterable
    {
        yield 'mysql' => ['SQL_FIXTURE_MYSQL', 'mysql'];
        yield 'postgres' => ['SQL_FIXTURE_PGSQL', 'pgsql'];
    }

    #[Test]
    public function testResolveVersionNamesTheDefaultOfTheDriver(): void
    {
        self::assertSame('mysql-8.4.7', PlatformFactory::resolveVersion(PlatformFactory::DRIVER_MYSQL)->tag);
        self::assertSame('pg-17.2', PlatformFactory::resolveVersion(PlatformFactory::DRIVER_PGSQL)->tag);
        self::assertSame('sqlite-3.47.2', PlatformFactory::resolveVersion(PlatformFactory::DRIVER_SQLITE)->tag);
        self::assertSame('5.6.51', PlatformFactory::resolveVersion(PlatformFactory::DRIVER_MYSQL, 'mysql-5.6.51')->number);
    }

    #[Test]
    public function testResolveVersionRejectsAnUnsupportedTag(): void
    {
        $this->expectException(\SqlFixture\Version\UnsupportedVersionException::class);
        PlatformFactory::resolveVersion(PlatformFactory::DRIVER_MYSQL, 'mysql-5.5.62');
    }

    #[Test]
    public function testCreateSchemaParserRejectsATagOfAnotherDriver(): void
    {
        $this->expectException(\SqlFixture\Version\UnsupportedVersionException::class);
        $this->expectExceptionMessage('Unsupported sqlite version: mysql-8.4.7');
        PlatformFactory::createSchemaParser(PlatformFactory::DRIVER_SQLITE, 'mysql-8.4.7');
    }

    #[Test]
    public function testCreateSchemaFetcherRejectsAnUnsupportedTag(): void
    {
        $this->expectException(\SqlFixture\Version\UnsupportedVersionException::class);
        PlatformFactory::createSchemaFetcher(PlatformFactory::DRIVER_PGSQL, 'pg-9.6.0');
    }

    #[Test]
    public function testDetectVersionMatchesTheLinkedSqlite(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $version = PlatformFactory::detectVersion($pdo);

        self::assertSame(PlatformFactory::DRIVER_SQLITE, $version->dialect);
        self::assertSame('sqlite-3.47.2', $version->tag);
    }

    #[Test]
    public function testDetectVersionResolvesAnExplicitTagAgainstTheDetectedDriver(): void
    {
        $pdo = new PDO('sqlite::memory:');

        self::assertSame('sqlite-3.47.2', PlatformFactory::detectVersion($pdo, 'sqlite-3.47.2')->tag);

        $this->expectException(\SqlFixture\Version\UnsupportedVersionException::class);
        PlatformFactory::detectVersion($pdo, 'mysql-8.4.7');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerDatabaseVersions')]
    public function testDetectVersionReadsTheNativeServer(string $prefix, string $driver, string $expected): void
    {
        $pdo = new PDO(
            (string) getenv($prefix . '_DSN'),
            (string) getenv($prefix . '_USER'),
            (string) getenv($prefix . '_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false],
        );
        $version = PlatformFactory::detectVersion($pdo);
        self::assertSame($driver, $version->dialect);
        self::assertSame($expected, $version->tag);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerDatabaseVersions(): iterable
    {
        yield 'mysql' => ['SQL_FIXTURE_MYSQL', 'mysql', 'mysql-8.4.7'];
        yield 'postgres' => ['SQL_FIXTURE_PGSQL', 'pgsql', 'pg-17.2'];
    }

    #[Test]
    public function testGetSupportedVersionsListsTheTagsOfADriver(): void
    {
        self::assertSame(['pg-17.2'], PlatformFactory::getSupportedVersions(PlatformFactory::DRIVER_PGSQL));
        self::assertContains('mysql-5.6.51', PlatformFactory::getSupportedVersions(PlatformFactory::DRIVER_MYSQL));
        self::assertContains('mysql-9.1.0', PlatformFactory::getSupportedVersions(PlatformFactory::DRIVER_MYSQL));
    }
}
