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

final class FixtureGenerator
{
    private TypeMapperInterface $typeMapper;
    private HydratorInterface $hydrator;
    private SchemaParserInterface $schemaParser;

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
     * @param array<string, mixed> $overrides Override values
     * @param class-string<T>|null $className Deserialization target class
     * @return ($className is null ? array<string, mixed> : T)
     */
    public function generate(
        TableSchema $schema,
        array $overrides = [],
        ?string $className = null,
    ): array|object {
        $this->assertOverridesFitSchema($schema, $overrides);

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
     * Refuse an override the table could not hold.
     *
     * Without this a misspelt column is dropped and the real one generated at
     * random, and a null lands in a NOT NULL column to fail much later at the
     * insert. Both look like working fixtures right up until they do not.
     *
     * @param array<string, mixed> $overrides
     * @throws InvalidOverrideException
     */
    private function assertOverridesFitSchema(TableSchema $schema, array $overrides): void
    {
        foreach ($overrides as $columnName => $value) {
            $column = $schema->getColumn($columnName);

            if ($column === null) {
                throw InvalidOverrideException::unknownColumn($columnName, $schema);
            }

            if ($column->generated) {
                throw InvalidOverrideException::generatedColumn($columnName, $schema);
            }

            if ($value === null && !$column->nullable) {
                throw InvalidOverrideException::notNullable($columnName, $schema);
            }
        }
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
