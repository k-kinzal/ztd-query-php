<?php

declare(strict_types=1);

namespace SqlFixture\Provider;

use Faker\Generator;
use SqlFixture\Fixture;
use SqlFixture\Hydrator\HydratorInterface;
use SqlFixture\Hydrator\ReflectionHydrator;
use SqlFixture\Platform\MySql\MySqlSchemaParser;
use SqlFixture\Platform\MySql\MySqlTypeMapper;
use SqlFixture\Schema\SchemaParserInterface;
use SqlFixture\Schema\TableSchema;
use SqlFixture\TypeMapper\TypeMapperInterface;

/**
 * Generates row data and optionally hydrates it into a consumer object.
 */
final class FixtureGenerator implements Fixture\RowGeneration
{
    private TypeMapperInterface $typeMapper;
    private HydratorInterface $hydrator;
    private SchemaParserInterface $schemaParser;

    /**
     * Initializes the collaborators and declared state for this object.
     */
    public function __construct(
        private readonly Generator $faker,
        ?TypeMapperInterface $typeMapper = null,
        ?HydratorInterface $hydrator = null,
        ?SchemaParserInterface $schemaParser = null,
    ) {
        $this->typeMapper = $typeMapper ?? new MySqlTypeMapper();
        $this->hydrator = $hydrator ?? new ReflectionHydrator();
        $this->schemaParser = $schemaParser ?? new MySqlSchemaParser();
    }

    /**
     * Generate fixture data from a parsed schema.
     *
     * @template T of object
     * @param TableSchema $schema Parsed table schema
     * @param array<mixed> $overrides Override values
     * @param class-string<T>|null $className Deserialization target class
     * @return ($className is null ? array<string, mixed> : T)
     */
    public function generate(
        TableSchema $schema,
        array $overrides = [],
        ?string $className = null,
    ): array|object {
        return (new Fixture\RowGenerator($this->faker, $this->typeMapper, $this->hydrator))->generate($schema, $overrides, $className);
    }

    /**
     * Get the schema parser instance.
     */
    public function getSchemaParser(): SchemaParserInterface
    {
        return $this->schemaParser;
    }

    /**
     * Get the type mapper instance.
     */
    public function getTypeMapper(): TypeMapperInterface
    {
        return $this->typeMapper;
    }

    /**
     * Get the hydrator instance.
     */
    public function getHydrator(): HydratorInterface
    {
        return $this->hydrator;
    }
}
