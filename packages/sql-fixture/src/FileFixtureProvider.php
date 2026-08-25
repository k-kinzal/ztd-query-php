<?php

declare(strict_types=1);

namespace SqlFixture;

use Faker\Generator;
use Faker\Provider\Base;
use RuntimeException;
use SqlFixture\Hydrator\HydratorInterface;
use SqlFixture\Platform\PlatformFactory;
use SqlFixture\Schema\DdlDirectory;
use SqlFixture\Schema\TableSchema;
use SqlFixture\TypeMapper\TypeMapperInterface;

/**
 * Faker provider that generates fixtures from local DDL files.
 */
class FileFixtureProvider extends Base
{
    private FixtureGenerator $fixtureGenerator;

    /** @var array<string, TableSchema> Table name → parsed schema cache */
    private array $schemas = [];

    /**
     * @param string $dialect SQL dialect ('mysql' or 'sqlite')
     */
    public function __construct(
        Generator $faker,
        string $ddlPath,
        ?TypeMapperInterface $typeMapper = null,
        ?HydratorInterface $hydrator = null,
        string $dialect = PlatformFactory::DRIVER_MYSQL,
    ) {
        parent::__construct($faker);

        $typeMapper ??= PlatformFactory::createTypeMapper($dialect);
        $schemaParser = PlatformFactory::createSchemaParser($dialect);

        $this->fixtureGenerator = new FixtureGenerator($faker, $typeMapper, $hydrator, $schemaParser);
        $this->schemas = (new DdlDirectory($this->fixtureGenerator->getSchemaParser()))->tables($ddlPath);
    }

    /**
     * Generate a fixture from a DDL file.
     *
     * @template T of object
     * @param string $tableName Table name (e.g., "users")
     * @param array<string, mixed> $overrides Override values
     * @param class-string<T>|null $className Deserialization target class
     * @return ($className is null ? array<string, mixed> : T)
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
}
