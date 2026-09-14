<?php

declare(strict_types=1);

namespace SqlFixture\Fixture\Exception;

use SqlFixture\InvalidOverrideException;
use SqlFixture\Schema\TableSchema;

/**
 * An override supplies null for a non-nullable column.
 */
final class NullOverrideException extends InvalidOverrideException
{
    /**
     * An override supplies null for a non-nullable column.
     */
    public function __construct(
        public readonly string $column,
        public readonly TableSchema $schema,
    ) {
        parent::__construct(sprintf(
            'Cannot override %s.%s with null: the column is NOT NULL.',
            $schema->tableName,
            $column
        ));
    }
}
