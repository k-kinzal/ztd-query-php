<?php

declare(strict_types=1);

namespace SqlFixture\Schema\Exception;

use SqlFixture\Schema\SchemaParseException;

/**
 * A table definition has no usable columns.
 */
final class MissingColumnDefinitionsException extends SchemaParseException
{
    /**
     * A table definition has no usable columns.
     */
    public function __construct(
        public readonly string $tableName,
    ) {
        parent::__construct(sprintf('No columns found in table: %s', $tableName));
    }
}
