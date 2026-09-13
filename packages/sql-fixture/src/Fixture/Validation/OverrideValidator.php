<?php

declare(strict_types=1);

namespace SqlFixture\Fixture\Validation;

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
     * @throws \SqlFixture\Fixture\Exception\UnknownOverrideColumnException
     * @throws \SqlFixture\Fixture\Exception\NullOverrideException
     * @throws \SqlFixture\Fixture\Exception\GeneratedColumnOverrideException
     */
    public function assertOverridesFitSchema(TableSchema $schema, array $overrides): void
    {
        foreach ($overrides as $columnName => $value) {
            if (!is_string($columnName)) {
                throw new \SqlFixture\Fixture\Exception\UnknownOverrideColumnException((string) $columnName, $schema);
            }
            $column = $schema->getColumn($columnName);

            if ($column === null) {
                throw new \SqlFixture\Fixture\Exception\UnknownOverrideColumnException($columnName, $schema);
            }

            if ($column->generated) {
                throw new \SqlFixture\Fixture\Exception\GeneratedColumnOverrideException($columnName, $schema);
            }

            if ($value === null && !$column->nullable) {
                throw new \SqlFixture\Fixture\Exception\NullOverrideException($columnName, $schema);
            }
        }
    }
}
