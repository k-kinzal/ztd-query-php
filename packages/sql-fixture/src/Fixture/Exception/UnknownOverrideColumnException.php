<?php

declare(strict_types=1);

namespace SqlFixture\Fixture\Exception;

use SqlFixture\InvalidOverrideException;
use SqlFixture\Schema\TableSchema;

/**
 * An override names a column absent from the schema.
 */
final class UnknownOverrideColumnException extends InvalidOverrideException
{
    /**
     * An override names a column absent from the schema.
     */
    public function __construct(
        public readonly string $column,
        public readonly TableSchema $schema,
    ) {
        parent::__construct(sprintf(
            'Cannot override %s.%s: there is no such column. Its columns are: %s.',
            $schema->tableName,
            $column,
            implode(', ', $schema->getColumnNames())
        ));
    }
}
