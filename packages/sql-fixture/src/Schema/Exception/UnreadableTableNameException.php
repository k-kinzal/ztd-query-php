<?php

declare(strict_types=1);

namespace SqlFixture\Schema\Exception;

use SqlFixture\Schema\SchemaParseException;

/**
 * A table name the grammar of the dialect cannot read as one table.
 */
final class UnreadableTableNameException extends SchemaParseException
{
    /**
     * A table name the grammar of the dialect cannot read as one table.
     */
    public function __construct(
        public readonly string $tableName,
    ) {
        parent::__construct(sprintf('Not a table name: %s', $tableName));
    }
}
