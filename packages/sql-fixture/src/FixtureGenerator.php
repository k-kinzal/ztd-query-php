<?php

declare(strict_types=1);

namespace SqlFixture;

use Faker\Generator;
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
        (new Fixture\Validation\OverrideValidator())->assertOverridesFitSchema($schema, $overrides);

        $data = [];

        foreach ($schema->columns as $column) {
            $columnName = $column->name;

            if (array_key_exists($columnName, $overrides)) {
                $data[$columnName] = $overrides[$columnName];
                continue;
            }

            if ($column->autoIncrement || $column->generated) {
                continue;
            }

            $data[$columnName] = $this->typeMapper->generate($this->faker, $column);
        }

        if ($className === null) {
            return $data;
        }

        return $this->hydrator->hydrate($data, $className);
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
