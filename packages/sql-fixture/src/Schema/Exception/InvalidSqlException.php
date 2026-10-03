<?php

declare(strict_types=1);

namespace SqlFixture\Schema\Exception;

use SqlFixture\Schema\SchemaParseException;
use Throwable;

/**
 * SQL cannot be parsed into a table definition.
 */
final class InvalidSqlException extends SchemaParseException
{
    /**
     * SQL cannot be parsed into a table definition.
     */
    public function __construct(
        public readonly string $sql,
        public readonly string $reason,
        ?Throwable $previous = null,
    ) {
        parent::__construct(sprintf('Failed to parse SQL: %s. SQL: %s', $reason, $sql), 0, $previous);
    }
}
