<?php

declare(strict_types=1);

namespace SqlFixture\Provider;

use Faker\Generator;
use Faker\Provider\Base;
use PDO;
use SqlFixture\Fixture;
use SqlFixture\Hydrator\HydratorInterface;
use SqlFixture\Provider;
use SqlFixture\Schema\SchemaFetcherInterface;
use SqlFixture\TypeMapper\TypeMapperInterface;
use SqlFixture\Version\ServerVersion;

/**
 * Faker provider that generates fixtures from database tables via PDO.
 *
 * Automatically detects the database driver (MySQL, PostgreSQL, SQLite) and
 * the release of the server, and uses the appropriate schema fetcher and
 * type mapper.
 *
 * @visibility public
 * @example Generate a fixture from a live SQLite table
 *     $pdo = new \PDO('sqlite::memory:');
 *     $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
 *     $provider = new \SqlFixture\Provider\DatabaseFixtureProvider(\Faker\Factory::create(), $pdo);
 *     $provider->fixture('users', ['name' => 'Alice']) // => ['name' => 'Alice']
 * @example Read the release of the connected server
 *     $provider = new \SqlFixture\Provider\DatabaseFixtureProvider(\Faker\Factory::create(), new \PDO('sqlite::memory:'));
 *     $provider->getVersion() // => 'sqlite-3.47.2'
 */
class DatabaseFixtureProvider extends Base
{
    private FixtureGenerator $fixtureGenerator;
    private SchemaFetcherInterface $schemaFetcher;
    private string $driver;
    private ServerVersion $version;

    private DatabaseSchemaCache $schemaCache;

    /**
     * Initializes the collaborators and declared state for this object.
     *
     * @param string|null $version Version tag such as 'mysql-8.4.7', or null to match the release the server reports
     *
     * @throws Exception\DriverDetectionException If the connection does not report a driver name or a server version
     * @throws Exception\UnsupportedDriverException If the driver of the connection is not supported
     * @throws \SqlFixture\Version\UnsupportedVersionException If the version tag is not a supported release of the driver
     */
    public function __construct(
        Generator $faker,
        PDO $connection,
        ?TypeMapperInterface $typeMapper = null,
        ?HydratorInterface $hydrator = null,
        ?SchemaFetcherInterface $schemaFetcher = null,
        ?string $version = null,
    ) {
        parent::__construct($faker);

        $this->driver = PlatformFactory::detectDriver($connection);
        $this->version = PlatformFactory::detectVersion($connection, $version);

        $typeMapper ??= PlatformFactory::createTypeMapper($this->driver);
        $schemaParser = PlatformFactory::createSchemaParser($this->driver, $this->version->tag);

        $this->schemaFetcher = $schemaFetcher ?? PlatformFactory::createSchemaFetcher($this->driver, $this->version->tag);
        $this->schemaCache = new DatabaseSchemaCache($connection, $this->schemaFetcher);
        $this->fixtureGenerator = new FixtureGenerator($faker, $typeMapper, $hydrator, $schemaParser);
    }

    /**
     * Generate a fixture from a database table.
     *
     * @template T of object
     * @param string $tableName Table name (e.g., "users" or "mydb.users")
     * @param array<string, mixed> $overrides Override values
     * @param class-string<T>|null $className Deserialization target class
     * @return ($className is null ? array<string, mixed> : T)
     */
    public function fixture(
        string $tableName,
        array $overrides = [],
        ?string $className = null,
    ): array|object {
        $schema = $this->schemaCache->getSchema($tableName);
        return $this->fixtureGenerator->generate($schema, $overrides, $className);
    }

    /**
     * Clear the schema cache.
     */
    public function clearCache(): void
    {
        $this->schemaCache->clear();
    }

    /**
     * Get the underlying fixture generator.
     */
    public function getFixtureGenerator(): FixtureGenerator
    {
        return $this->fixtureGenerator;
    }

    /**
     * Get the detected driver name.
     */
    public function getDriver(): string
    {
        return $this->driver;
    }

    /**
     * Get the version tag of the release of the connected server.
     */
    public function getVersion(): string
    {
        return $this->version->tag;
    }
}
