<?php

declare(strict_types=1);

namespace SqlFixture\Fixture\Validation;

use SqlFixture\InvalidOverrideException;
use SqlFixture\Schema\TableSchema;

/**
 * Rejects overrides that the declared table cannot store.
 *
 */
final class OverrideValidator
{
    /**
     * Refuse an override the table could not hold.
     *
     * Without this a misspelt column is dropped and the real one generated at
     * random, and a null lands in a NOT NULL column to fail much later at the
     * insert. Both look like working fixtures right up until they do not.
     *
     * @param array<mixed> $overrides
     * @throws InvalidOverrideException
     */
    public function assertOverridesFitSchema(TableSchema $schema, array $overrides): void
    {
        foreach ($overrides as $columnName => $value) {
            if (!is_string($columnName)) {
                throw InvalidOverrideException::unknownColumn((string) $columnName, $schema);
            }
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
}
