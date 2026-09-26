<?php

declare(strict_types=1);

namespace SqlFixture\Provider;

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
use SqlFixture\Provider\Exception\DriverDetectionException;
use SqlFixture\Provider\Exception\UnsupportedDriverException;
use SqlFixture\Schema\SchemaFetcherInterface;
use SqlFixture\Schema\SchemaParserInterface;
use SqlFixture\TypeMapper\TypeMapperInterface;
use SqlFixture\Version\ServerVersion;
use SqlFixture\Version\UnsupportedVersionException;

/**
 * Factory for creating platform-specific implementations.
 *
 * Every platform is selected by its PDO driver name and, optionally, by the
 * version tag of the release the statements are read for.
 *
 * @visibility public
 * @example Resolve the release a version tag names
 *     \SqlFixture\Provider\PlatformFactory::resolveVersion('mysql', 'mysql-8.0.44')->number // => '8.0.44'
 * @example Read the release from a connection
 *     \SqlFixture\Provider\PlatformFactory::detectVersion(new \PDO('sqlite::memory:'))->tag // => 'sqlite-3.47.2'
 */
final class PlatformFactory
{
    /**
     * PDO driver name for MySQL.
     */
    public const DRIVER_MYSQL = 'mysql';
    /**
     * PDO driver name for SQLite.
     */
    public const DRIVER_SQLITE = 'sqlite';
    /**
     * PDO driver name for PostgreSQL.
     */
    public const DRIVER_PGSQL = 'pgsql';

    /**
     * Create a schema parser for the given driver and release.
     *
     * @param string|null $version Version tag such as `mysql-8.4.7`, or null for the default of the driver
     *
     * @throws UnsupportedDriverException If the driver is not supported
     * @throws UnsupportedVersionException If the version tag is not a supported release of the driver
     */
    public static function createSchemaParser(string $driver, ?string $version = null): SchemaParserInterface
    {
        self::resolveVersion($driver, $version);

        return match ($driver) {
            self::DRIVER_MYSQL => new MySqlSchemaParser(),
            self::DRIVER_SQLITE => new SqliteSchemaParser(),
            self::DRIVER_PGSQL => new PostgreSqlSchemaParser(),
            default => throw new UnsupportedDriverException($driver),
        };
    }

    /**
     * Create a type mapper for the given driver.
     *
     * @throws UnsupportedDriverException If the driver is not supported
     */
    public static function createTypeMapper(string $driver): TypeMapperInterface
    {
        return match ($driver) {
            self::DRIVER_MYSQL => new MySqlTypeMapper(),
            self::DRIVER_SQLITE => new SqliteTypeMapper(),
            self::DRIVER_PGSQL => new PostgreSqlTypeMapper(),
            default => throw new UnsupportedDriverException($driver),
        };
    }

    /**
     * Create a schema fetcher for the given driver and release.
     *
     * @param string|null $version Version tag such as `mysql-8.4.7`, or null for the default of the driver
     *
     * @throws UnsupportedDriverException If the driver is not supported
     * @throws UnsupportedVersionException If the version tag is not a supported release of the driver
     */
    public static function createSchemaFetcher(string $driver, ?string $version = null): SchemaFetcherInterface
    {
        self::resolveVersion($driver, $version);

        return match ($driver) {
            self::DRIVER_MYSQL => new MySqlSchemaFetcher(),
            self::DRIVER_SQLITE => new SqliteSchemaFetcher(),
            self::DRIVER_PGSQL => new PostgreSqlSchemaFetcher(),
            default => throw new UnsupportedDriverException($driver),
        };
    }

    /**
     * Resolve the release a version tag names, the default of the driver when none is given.
     *
     * @param string|null $version Version tag such as `mysql-8.4.7`, or null for the default of the driver
     *
     * @throws UnsupportedDriverException If the driver is not supported
     * @throws UnsupportedVersionException If the version tag is not a supported release of the driver
     */
    public static function resolveVersion(string $driver, ?string $version = null): ServerVersion
    {
        if (!in_array($driver, self::getSupportedDrivers(), true)) {
            throw new UnsupportedDriverException($driver);
        }

        return ServerVersion::resolve($driver, $version);
    }

    /**
     * Detect the driver name from a PDO connection.
     *
     * @throws DriverDetectionException If the connection does not report a driver name
     * @throws UnsupportedDriverException If the reported driver is not supported
     */
    public static function detectDriver(PDO $pdo): string
    {
        $driverName = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if (!is_string($driverName)) {
            throw new DriverDetectionException();
        }

        return match ($driverName) {
            'mysql' => self::DRIVER_MYSQL,
            'sqlite' => self::DRIVER_SQLITE,
            'pgsql' => self::DRIVER_PGSQL,
            default => throw new UnsupportedDriverException($driverName),
        };
    }

    /**
     * Detect the release of the server behind a PDO connection.
     *
     * The server version the connection reports is matched to the closest
     * supported release, as `ServerVersion::fromServer()` describes. A version
     * tag given explicitly is resolved against the detected driver instead.
     *
     * @param string|null $version Version tag such as `mysql-8.4.7`, or null to read the server version
     *
     * @throws DriverDetectionException If the connection does not report a driver name or a server version
     * @throws UnsupportedDriverException If the reported driver is not supported
     * @throws UnsupportedVersionException If the version tag is not a supported release of the driver
     */
    public static function detectVersion(PDO $pdo, ?string $version = null): ServerVersion
    {
        $driver = self::detectDriver($pdo);
        if ($version !== null) {
            return ServerVersion::resolve($driver, $version);
        }

        $reported = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
        if (!is_string($reported)) {
            throw new DriverDetectionException();
        }

        return ServerVersion::fromServer($driver, $reported);
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

    /**
     * Get the version tags of the releases supported for a driver, oldest first.
     *
     * @return non-empty-list<string> Version tags such as `mysql-8.4.7`
     *
     * @throws UnsupportedDriverException If the driver is not supported
     */
    public static function getSupportedVersions(string $driver): array
    {
        if (!in_array($driver, self::getSupportedDrivers(), true)) {
            throw new UnsupportedDriverException($driver);
        }

        return ServerVersion::tags($driver);
    }
}
