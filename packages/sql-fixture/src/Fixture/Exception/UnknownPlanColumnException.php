<?php

declare(strict_types=1);

namespace SqlFixture\Fixture\Exception;

use SqlFixture\Fixture\PlanSchemaException;
use SqlFixture\Plan\ColumnRef;
use SqlFixture\Schema\TableSchema;

/**
 * A relation names a column absent from the schema.
 */
final class UnknownPlanColumnException extends PlanSchemaException
{
    /**
     * A relation names a column absent from the schema.
     */
    public function __construct(
        public readonly ColumnRef $reference,
        public readonly string $column,
        public readonly TableSchema $schema,
    ) {
        parent::__construct(sprintf(
            'The plan links %s, but %s has no column %s. Its columns are: %s.',
            $reference->toString(),
            $schema->tableName,
            $column,
            implode(', ', $schema->getColumnNames())
        ));
    }
}
