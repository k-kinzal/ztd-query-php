<?php

declare(strict_types=1);

namespace SqlFixture\Schema\Exception;

use SqlFixture\Schema\SchemaParseException;

/**
 * SQL does not contain a CREATE TABLE statement.
 */
final class ExpectedCreateTableException extends SchemaParseException
{
    /**
     * SQL does not contain a CREATE TABLE statement.
     */
    public function __construct(
        public readonly string $sql,
    ) {
        parent::__construct(sprintf('Expected CREATE TABLE statement, got: %s', $sql));
    }
}
