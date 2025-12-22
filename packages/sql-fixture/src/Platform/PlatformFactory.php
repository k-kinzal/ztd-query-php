<?php

declare(strict_types=1);

namespace SqlFixture\Platform;

use InvalidArgumentException;
use PDO;
use SqlFixture\Platform\MySql\MySqlSchemaFetcher;
use SqlFixture\Platform\MySql\MySqlSchemaParser;
use SqlFixture\Platform\MySql\MySqlTypeMapper;
use SqlFixture\Platform\PostgreSql\PostgreSqlSchemaFetcher;
use SqlFixture\Platform\PostgreSql\PostgreSqlSchemaParser;
use SqlFixture\Platform\PostgreSql\PostgreSqlTypeMapper;
use SqlFixture\Platform\Sqlite\SqliteSchemaFetcher;
use SqlFixture\Platform\Sqlite\SqliteSchemaParser;
use SqlFixture\Platform\Sqlite\SqliteTypeMapper;
use SqlFixture\Schema\SchemaFetcherInterface;
use SqlFixture\Schema\SchemaParserInterface;
use SqlFixture\TypeMapper\TypeMapperInterface;

/**
 * Factory for creating platform-specific implementations.
 */
final class PlatformFactory
{
    public const DRIVER_MYSQL = 'mysql';
    public const DRIVER_SQLITE = 'sqlite';
    public const DRIVER_PGSQL = 'pgsql';

    /**
     * Create a schema parser for the given driver.
     *
     * @throws InvalidArgumentException If the driver is not supported
     */
    public static function createSchemaParser(string $driver): SchemaParserInterface
    {
        return match ($driver) {
            self::DRIVER_MYSQL => new MySqlSchemaParser(),
            self::DRIVER_SQLITE => new SqliteSchemaParser(),
            self::DRIVER_PGSQL => new PostgreSqlSchemaParser(),
            default => throw new InvalidArgumentException("Unsupported driver: {$driver}"),
        };
    }

    /**
     * Create a type mapper for the given driver.
     *
     * @throws InvalidArgumentException If the driver is not supported
     */
    public static function createTypeMapper(string $driver): TypeMapperInterface
    {
        return match ($driver) {
            self::DRIVER_MYSQL => new MySqlTypeMapper(),
            self::DRIVER_SQLITE => new SqliteTypeMapper(),
            self::DRIVER_PGSQL => new PostgreSqlTypeMapper(),
            default => throw new InvalidArgumentException("Unsupported driver: {$driver}"),
        };
    }

    /**
     * Create a schema fetcher for the given driver.
     *
     * @throws InvalidArgumentException If the driver is not supported
     */
    public static function createSchemaFetcher(string $driver): SchemaFetcherInterface
    {
        return match ($driver) {
            self::DRIVER_MYSQL => new MySqlSchemaFetcher(),
            self::DRIVER_SQLITE => new SqliteSchemaFetcher(),
            self::DRIVER_PGSQL => new PostgreSqlSchemaFetcher(),
            default => throw new InvalidArgumentException("Unsupported driver: {$driver}"),
        };
    }

    /**
     * Detect the driver name from a PDO connection.
     *
     * @throws InvalidArgumentException If the driver cannot be detected
     */
    public static function detectDriver(PDO $pdo): string
    {
        $driverName = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if (!is_string($driverName)) {
            throw new InvalidArgumentException('Unable to detect PDO driver');
        }

        return match ($driverName) {
            'mysql' => self::DRIVER_MYSQL,
            'sqlite' => self::DRIVER_SQLITE,
            'pgsql' => self::DRIVER_PGSQL,
            default => throw new InvalidArgumentException("Unsupported PDO driver: {$driverName}"),
        };
    }

    /**
     * Get the list of supported drivers.
     *
     * @return list<string>
     */
    public static function getSupportedDrivers(): array
    {
        return [
            self::DRIVER_MYSQL,
            self::DRIVER_SQLITE,
            self::DRIVER_PGSQL,
        ];
    }
}
