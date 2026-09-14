<?php

declare(strict_types=1);

namespace SqlFixture\Fixture\Exception;

use SqlFixture\Fixture\PlanSchemaException;
use SqlFixture\Plan\ColumnRef;
use SqlFixture\Schema\TableSchema;

/**
 * A relation names a column computed by the database.
 */
final class GeneratedColumnReferenceException extends PlanSchemaException
{
    /**
     * A relation names a column computed by the database.
     */
    public function __construct(
        public readonly ColumnRef $reference,
        public readonly string $column,
        public readonly TableSchema $schema,
    ) {
        parent::__construct(sprintf(
            'The plan links %s, but %s.%s is a generated column: the database computes '
            . 'it, so there is no value to carry across the relation and none to write '
            . 'into it. Link a stored column instead.',
            $reference->toString(),
            $schema->tableName,
            $column
        ));
    }
}
