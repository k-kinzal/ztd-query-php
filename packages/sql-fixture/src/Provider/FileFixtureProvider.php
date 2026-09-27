<?php

declare(strict_types=1);

namespace SqlFixture\Provider;

use Faker\Generator;
use Faker\Provider\Base;
use RuntimeException;
use SqlFixture\Fixture;
use SqlFixture\Hydrator\HydratorInterface;
use SqlFixture\Provider;
use SqlFixture\Schema\TableSchema;
use SqlFixture\TypeMapper\TypeMapperInterface;
use SqlFixture\Version\ServerVersion;

/**
 * Faker provider that generates fixtures from local DDL files.
 *
 * The dialect and, optionally, the version tag select the release the DDL
 * files are read for; omitting the version tag uses the default of the dialect.
 */
class FileFixtureProvider extends Base
{
    private FixtureGenerator $fixtureGenerator;
    private string $dialect;
    private ServerVersion $version;

    /**
     * @var array<string, TableSchema> Table name → parsed schema cache
     */
    private array $schemas = [];

    /**
     * @param string $dialect SQL dialect: 'mysql', 'pgsql' or 'sqlite'
     * @param string|null $version Version tag such as 'mysql-8.4.7', or null for the default of the dialect
     *
     * @throws Exception\UnsupportedDriverException If the dialect is not supported
     * @throws \SqlFixture\Version\UnsupportedVersionException If the version tag is not a supported release of the dialect
     */
    public function __construct(
        Generator $faker,
        string $ddlPath,
        ?TypeMapperInterface $typeMapper = null,
        ?HydratorInterface $hydrator = null,
        string $dialect = PlatformFactory::DRIVER_MYSQL,
        ?string $version = null,
    ) {
        parent::__construct($faker);
        $this->dialect = $dialect;
        $this->version = PlatformFactory::resolveVersion($dialect, $version);

        $typeMapper ??= PlatformFactory::createTypeMapper($dialect);
        $schemaParser = PlatformFactory::createSchemaParser($dialect, $this->version->tag);

        $this->fixtureGenerator = new FixtureGenerator($faker, $typeMapper, $hydrator, $schemaParser);
        $this->schemas = (new DdlDirectory())->loadSchemas($ddlPath, $schemaParser);
    }

    /**
     * Generate a fixture from a DDL file.
     *
     * @template T of object
     * @param string $tableName Table name (e.g., "users")
     * @param array<string, mixed> $overrides Override values
     * @param class-string<T>|null $className Deserialization target class
     * @return ($className is null ? array<string, mixed> : T)
     * @throws RuntimeException
     */
    public function fixture(
        string $tableName,
        array $overrides = [],
        ?string $className = null,
    ): array|object {
        $normalizedName = strtolower($tableName);

        if (!isset($this->schemas[$normalizedName])) {
            throw new RuntimeException("Schema not found for table: {$tableName}");
        }

        return $this->fixtureGenerator->generate($this->schemas[$normalizedName], $overrides, $className);
    }

    /**
     * Check if a table schema is loaded.
     */
    public function hasTable(string $tableName): bool
    {
        return isset($this->schemas[strtolower($tableName)]);
    }

    /**
     * Get list of available table names.
     *
     * @return list<string>
     */
    public function getTableNames(): array
    {
        return array_keys($this->schemas);
    }

    /**
     * Manually register a schema.
     */
    public function registerSchema(string $createTableSql): void
    {
        $schema = $this->fixtureGenerator->getSchemaParser()->parse($createTableSql);
        $this->schemas[strtolower($schema->tableName)] = $schema;
    }

    /**
     * Get the underlying fixture generator.
     */
    public function getFixtureGenerator(): FixtureGenerator
    {
        return $this->fixtureGenerator;
    }

    /**
     * Get the dialect the DDL files are read for.
     */
    public function getDialect(): string
    {
        return $this->dialect;
    }

    /**
     * Get the version tag of the release the DDL files are read for.
     */
    public function getVersion(): string
    {
        return $this->version->tag;
    }
}
