<?php

declare(strict_types=1);

namespace SqlFixture;

use Faker\Generator;
use Faker\Provider\Base;
use SqlFixture\Hydrator\HydratorInterface;
use SqlFixture\Platform\PlatformFactory;
use SqlFixture\Schema\SchemaParserInterface;
use SqlFixture\Schema\TableSchema;
use SqlFixture\TypeMapper\TypeMapperInterface;

/**
 * Faker provider that generates fixtures from CREATE TABLE SQL statements.
 */
class FixtureProvider extends Base
{
    private FixtureGenerator $fixtureGenerator;
    private string $dialect;

    /** @var array<string, TableSchema> Schema cache by SQL hash */
    private array $schemaCache = [];

    /**
     * @param string $dialect SQL dialect ('mysql' or 'sqlite')
     */
    public function __construct(
        Generator $faker,
        ?TypeMapperInterface $typeMapper = null,
        ?HydratorInterface $hydrator = null,
        ?SchemaParserInterface $schemaParser = null,
        string $dialect = PlatformFactory::DRIVER_MYSQL,
    ) {
        parent::__construct($faker);
        $this->dialect = $dialect;

        $typeMapper ??= PlatformFactory::createTypeMapper($dialect);
        $schemaParser ??= PlatformFactory::createSchemaParser($dialect);

        $this->fixtureGenerator = new FixtureGenerator($faker, $typeMapper, $hydrator, $schemaParser);
    }

    /**
     * Generate a fixture from a CREATE TABLE SQL statement.
     *
     * @template T of object
     * @param string $createTableSql CREATE TABLE SQL statement
     * @param array<string, mixed> $overrides Override values
     * @param class-string<T>|null $className Deserialization target class
     * @param string|null $dialect SQL dialect for this specific call (overrides constructor default)
     * @return ($className is null ? array<string, mixed> : T)
     */
    public function fixture(
        string $createTableSql,
        array $overrides = [],
        ?string $className = null,
        ?string $dialect = null,
    ): array|object {
        $schema = $this->getSchema($createTableSql, $dialect);
        return $this->fixtureGenerator->generate($schema, $overrides, $className);
    }

    /**
     * Get or parse schema from SQL.
     */
    protected function getSchema(string $createTableSql, ?string $dialect = null): TableSchema
    {
        $effectiveDialect = $dialect ?? $this->dialect;
        $cacheKey = md5($createTableSql . ':' . $effectiveDialect);

        if (!isset($this->schemaCache[$cacheKey])) {
            $parser = ($effectiveDialect !== $this->dialect)
                ? PlatformFactory::createSchemaParser($effectiveDialect)
                : $this->fixtureGenerator->getSchemaParser();

            $this->schemaCache[$cacheKey] = $parser->parse($createTableSql);
        }

        return $this->schemaCache[$cacheKey];
    }

    /**
     * Get the underlying fixture generator.
     */
    public function getFixtureGenerator(): FixtureGenerator
    {
        return $this->fixtureGenerator;
    }

    /**
     * Get the default dialect.
     */
    public function getDialect(): string
    {
        return $this->dialect;
    }
}
