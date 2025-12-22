<?php

declare(strict_types=1);

namespace SqlFixture;

use Faker\Generator;
use Faker\Provider\Base;
use PDO;
use SqlFixture\Hydrator\HydratorInterface;
use SqlFixture\Platform\PlatformFactory;
use SqlFixture\Schema\SchemaFetcherInterface;
use SqlFixture\Schema\TableSchema;
use SqlFixture\TypeMapper\TypeMapperInterface;

/**
 * Faker provider that generates fixtures from database tables via PDO.
 *
 * Automatically detects the database driver (MySQL, SQLite) and uses
 * the appropriate schema fetcher and type mapper.
 */
class DatabaseFixtureProvider extends Base
{
    private FixtureGenerator $fixtureGenerator;
    private SchemaFetcherInterface $schemaFetcher;
    private PDO $connection;
    private string $driver;

    /** @var array<string, TableSchema> Table name → parsed schema cache */
    private array $schemaCache = [];

    public function __construct(
        Generator $faker,
        PDO $connection,
        ?TypeMapperInterface $typeMapper = null,
        ?HydratorInterface $hydrator = null,
        ?SchemaFetcherInterface $schemaFetcher = null,
    ) {
        parent::__construct($faker);

        $this->connection = $connection;
        $this->driver = PlatformFactory::detectDriver($connection);

        $typeMapper ??= PlatformFactory::createTypeMapper($this->driver);
        $schemaParser = PlatformFactory::createSchemaParser($this->driver);

        $this->schemaFetcher = $schemaFetcher ?? PlatformFactory::createSchemaFetcher($this->driver);
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
        $schema = $this->getSchema($tableName);
        return $this->fixtureGenerator->generate($schema, $overrides, $className);
    }

    /**
     * Get or fetch schema for a table.
     */
    private function getSchema(string $tableName): TableSchema
    {
        $normalizedName = $this->normalizeTableName($tableName);

        if (!isset($this->schemaCache[$normalizedName])) {
            $this->schemaCache[$normalizedName] = $this->schemaFetcher->fetchSchema(
                $this->connection,
                $tableName
            );
        }

        return $this->schemaCache[$normalizedName];
    }

    /**
     * Normalize table name for caching.
     */
    private function normalizeTableName(string $tableName): string
    {
        return str_replace(['`', '"'], '', $tableName);
    }

    /**
     * Clear the schema cache.
     */
    public function clearCache(): void
    {
        $this->schemaCache = [];
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
