<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\MySqlSchemaFetcher;
use SqlFixture\Platform\MySql\MySqlSchemaParser;
use SqlFixture\Platform\MySql\MySqlTypeMapper;
use SqlFixture\Platform\PlatformFactory;
use SqlFixture\Platform\PostgreSql\PostgreSqlSchemaFetcher;
use SqlFixture\Platform\PostgreSql\PostgreSqlSchemaParser;
use SqlFixture\Platform\PostgreSql\PostgreSqlTypeMapper;
use SqlFixture\Platform\Sqlite\SqliteSchemaFetcher;
use SqlFixture\Platform\Sqlite\SqliteSchemaParser;
use SqlFixture\Platform\Sqlite\SqliteTypeMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(PlatformFactory::class)]
#[UsesClass(SqliteSchemaFetcher::class)]
#[UsesClass(PostgreSqlSchemaFetcher::class)]
#[UsesClass(MySqlSchemaFetcher::class)]
final class PlatformFactoryTest extends TestCase
{
    #[Test]
    public function createSchemaParserForMysql(): void
    {
        $parser = PlatformFactory::createSchemaParser(PlatformFactory::DRIVER_MYSQL);

        self::assertInstanceOf(MySqlSchemaParser::class, $parser);
    }

    #[Test]
    public function createSchemaParserForSqlite(): void
    {
        $parser = PlatformFactory::createSchemaParser(PlatformFactory::DRIVER_SQLITE);

        self::assertInstanceOf(SqliteSchemaParser::class, $parser);
    }

    #[Test]
    public function createSchemaParserForPgsql(): void
    {
        $parser = PlatformFactory::createSchemaParser(PlatformFactory::DRIVER_PGSQL);

        self::assertInstanceOf(PostgreSqlSchemaParser::class, $parser);
    }

    #[Test]
    public function createSchemaParserThrowsForUnsupportedDriver(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported driver: oracle');

        PlatformFactory::createSchemaParser('oracle');
    }

    #[Test]
    public function createTypeMapperForMysql(): void
    {
        $mapper = PlatformFactory::createTypeMapper(PlatformFactory::DRIVER_MYSQL);

        self::assertInstanceOf(MySqlTypeMapper::class, $mapper);
    }

    #[Test]
    public function createTypeMapperForSqlite(): void
    {
        $mapper = PlatformFactory::createTypeMapper(PlatformFactory::DRIVER_SQLITE);

        self::assertInstanceOf(SqliteTypeMapper::class, $mapper);
    }

    #[Test]
    public function createTypeMapperForPgsql(): void
    {
        $mapper = PlatformFactory::createTypeMapper(PlatformFactory::DRIVER_PGSQL);

        self::assertInstanceOf(PostgreSqlTypeMapper::class, $mapper);
    }

    #[Test]
    public function createTypeMapperThrowsForUnsupportedDriver(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported driver: oracle');

        PlatformFactory::createTypeMapper('oracle');
    }

    #[Test]
    public function createSchemaFetcherForMysql(): void
    {
        $fetcher = PlatformFactory::createSchemaFetcher(PlatformFactory::DRIVER_MYSQL);

        self::assertInstanceOf(MySqlSchemaFetcher::class, $fetcher);
    }

    #[Test]
    public function createSchemaFetcherForSqlite(): void
    {
        $fetcher = PlatformFactory::createSchemaFetcher(PlatformFactory::DRIVER_SQLITE);

        self::assertInstanceOf(SqliteSchemaFetcher::class, $fetcher);
    }

    #[Test]
    public function createSchemaFetcherForPgsql(): void
    {
        $fetcher = PlatformFactory::createSchemaFetcher(PlatformFactory::DRIVER_PGSQL);

        self::assertInstanceOf(PostgreSqlSchemaFetcher::class, $fetcher);
    }

    #[Test]
    public function createSchemaFetcherThrowsForUnsupportedDriver(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported driver: oracle');

        PlatformFactory::createSchemaFetcher('oracle');
    }

    #[Test]
    public function detectDriverForSqlite(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $driver = PlatformFactory::detectDriver($pdo);

        self::assertSame(PlatformFactory::DRIVER_SQLITE, $driver);
    }

    #[Test]
    public function getSupportedDrivers(): void
    {
        $drivers = PlatformFactory::getSupportedDrivers();

        self::assertContains(PlatformFactory::DRIVER_MYSQL, $drivers);
        self::assertContains(PlatformFactory::DRIVER_SQLITE, $drivers);
        self::assertContains(PlatformFactory::DRIVER_PGSQL, $drivers);
    }
}
