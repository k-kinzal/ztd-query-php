<?php

declare(strict_types=1);

namespace SqlFixture\Fixture;

use RuntimeException;
use SqlFixture\Plan\ColumnRef;
use SqlFixture\Schema\TableSchema;

/**
 * A plan that reads correctly but does not match the tables it names.
 */
final class PlanSchemaException extends RuntimeException
{
    public static function unknownColumn(ColumnRef $reference, string $column, TableSchema $schema): self
    {
        return new self(sprintf(
            'The plan links %s, but %s has no column %s. Its columns are: %s.',
            $reference->toString(),
            $schema->tableName,
            $column,
            implode(', ', $schema->getColumnNames())
        ));
    }
}
