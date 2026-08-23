<?php

declare(strict_types=1);

namespace SqlFixture;

use Faker\Generator;
use Faker\Provider\Base;
use SqlFixture\Fixture\FixtureSet;
use SqlFixture\Fixture\PlanGenerator;
use SqlFixture\Fixture\TableOverrides;
use SqlFixture\Hydrator\HydratorInterface;
use SqlFixture\Plan\FixturePlan;
use SqlFixture\Platform\PlatformFactory;
use SqlFixture\Schema\SchemaParserInterface;
use SqlFixture\Schema\StaticSchemaResolver;
use SqlFixture\Schema\TableSchema;
use SqlFixture\TypeMapper\TypeMapperInterface;

/**
 * Faker provider that generates fixtures from CREATE TABLE SQL statements.
 */
class FixtureProvider extends Base
{
    private FixtureGenerator $fixtureGenerator;
    private string $dialect;
    private Generator $faker;
    private StaticSchemaResolver $schemaResolver;

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
        $this->faker = $faker;
        $this->dialect = $dialect;
        $this->schemaResolver = new StaticSchemaResolver();

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
     * Generate the rows a plan describes.
     *
     * Every table the plan names has to have been registered, either through
     * registerSchema() or by generating a fixture from its DDL first.
     *
     * @param FixturePlan|string $plan A plan, or the relation syntax for one
     * @param array<string, int|array<mixed>|TableOverrides> $overrides Table name => what to override
     */
    public function fixtures(FixturePlan|string $plan, array $overrides = []): FixtureSet
    {
        $generator = new PlanGenerator($this->schemaResolver, $this->fixtureGenerator, $this->faker);

        return $generator->generate(FixturePlan::from($plan), $overrides);
    }

    /**
     * Make a table available to fixtures() under its own name.
     */
    public function registerSchema(string $createTableSql): TableSchema
    {
        return $this->getSchema($createTableSql);
    }

    /**
     * The registry backing fixtures().
     */
    public function getSchemaResolver(): StaticSchemaResolver
    {
        return $this->schemaResolver;
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

            $schema = $parser->parse($createTableSql);
            $this->schemaCache[$cacheKey] = $schema;
            $this->schemaResolver->register($schema);
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
