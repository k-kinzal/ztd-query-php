<?php

declare(strict_types=1);

namespace SqlFixture;

use Faker\Generator;
use Faker\Provider\Base;
use PDO;
use SqlFixture\Hydrator\HydratorInterface;
use SqlFixture\Platform\PlatformFactory;
use SqlFixture\Schema\SchemaFetcherInterface;
use SqlFixture\TypeMapper\TypeMapperInterface;

/**
 * Faker provider that generates fixtures from database tables via PDO.
 *
 * Automatically detects the database driver (MySQL, SQLite) and uses
 * the appropriate schema fetcher and type mapper.
 *
 * @visibility public
 * @example Generate a fixture from a live SQLite table
 *     $pdo = new \PDO('sqlite::memory:');
 *     $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
 *     $provider = new \SqlFixture\DatabaseFixtureProvider(\Faker\Factory::create(), $pdo);
 *     $provider->fixture('users', ['name' => 'Alice']) // => ['name' => 'Alice']
 */
class DatabaseFixtureProvider extends Base
{
    private FixtureGenerator $fixtureGenerator;
    private SchemaFetcherInterface $schemaFetcher;
    private string $driver;

    private Provider\DatabaseSchemaCache $schemaCache;

    /**
     * Initializes the collaborators and declared state for this object.
     */
    public function __construct(
        Generator $faker,
        PDO $connection,
        ?TypeMapperInterface $typeMapper = null,
        ?HydratorInterface $hydrator = null,
        ?SchemaFetcherInterface $schemaFetcher = null,
    ) {
        parent::__construct($faker);

        $this->driver = PlatformFactory::detectDriver($connection);

        $typeMapper ??= PlatformFactory::createTypeMapper($this->driver);
        $schemaParser = PlatformFactory::createSchemaParser($this->driver);

        $this->schemaFetcher = $schemaFetcher ?? PlatformFactory::createSchemaFetcher($this->driver);
        $this->schemaCache = new Provider\DatabaseSchemaCache($connection, $this->schemaFetcher);
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
}
