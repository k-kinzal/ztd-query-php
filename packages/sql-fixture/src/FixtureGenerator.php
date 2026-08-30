<?php

declare(strict_types=1);

namespace SqlFixture;

use Faker\Generator;
use SqlFixture\Fixture\RowGenerator;
use SqlFixture\Fixture\RowSpec;
use SqlFixture\Hydrator\HydrationException;
use SqlFixture\Hydrator\HydratorInterface;
use SqlFixture\Hydrator\ReflectionHydrator;
use SqlFixture\Platform\MySql\MySqlSchemaParser;
use SqlFixture\Platform\MySql\MySqlTypeMapper;
use SqlFixture\Schema\SchemaParserInterface;
use SqlFixture\Schema\TableSchema;
use SqlFixture\TypeMapper\TypeMapperInterface;

/**
 * Builds one row for a table, generating every column the caller did not fix.
 *
 * This is where a table declaration meets the choices a caller made: the
 * schema says what columns exist and what they hold, the type mapper says what
 * a column of that kind is given, and the overrides say which columns the
 * caller wanted to decide themselves.
 *
 * @phpstan-import-type FixtureRow from TypeMapperInterface
 */
final class FixtureGenerator implements RowGenerator
{
    private TypeMapperInterface $typeMapper;
    private HydratorInterface $hydrator;
    private SchemaParserInterface $schemaParser;

    /**
     * Builds a generator for one dialect.
     *
     * @param Generator $faker Source of every choice a generated column makes
     * @param TypeMapperInterface|null $typeMapper Decides what a column of each type is given
     * @param HydratorInterface|null $hydrator Turns a row into an object
     * @param SchemaParserInterface|null $schemaParser Reads a declaration as a table
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
     * Builds one row for a table.
     *
     * A column the caller fixed takes the value they gave; every other column
     * is generated from its declared type. A column the server fills in is
     * given null, which is how the row says to leave it alone.
     *
     * @template T of object
     * @param TableSchema $schema Table to build a row for
     * @param array<array-key, mixed> $overrides Columns the caller fixes, instead of generating them
     * @param class-string<T>|null $className Class to hydrate the row into, or null for the row itself
     *
     * @return ($className is null ? FixtureRow : T) The row, or the object it was hydrated into
     *
     * @throws InvalidOverrideException When an override names a column the table cannot hold
     * @throws HydrationException When the row cannot be turned into the class named
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
                $data[$columnName] = RowSpec::asOverride($columnName, $overrides[$columnName]);
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
     * Refuses an override the table cannot hold.
     *
     * Without this a misspelt column is dropped and the real one generated at
     * random, and a null lands in a NOT NULL column to fail much later at the
     * insert. Both look like working fixtures right up until they do not.
     *
     * @param TableSchema $schema Table the row is for
     * @param array<array-key, mixed> $overrides Values the caller fixed
     *
     * @throws InvalidOverrideException When a column is unknown, is filled by the server, or cannot be null
     */
    public function assertOverridesFitSchema(TableSchema $schema, array $overrides): void
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
