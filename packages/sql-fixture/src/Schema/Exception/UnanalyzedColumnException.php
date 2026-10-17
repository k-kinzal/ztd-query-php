<?php

declare(strict_types=1);

namespace SqlFixture\Schema\Exception;

use SqlFixture\Schema\SchemaParseException;

/**
 * A column of a table definition could not be analyzed, so no schema is read rather than an incomplete one.
 */
final class UnanalyzedColumnException extends SchemaParseException
{
    /**
     * A column of a table definition could not be analyzed.
     */
    public function __construct(
        public readonly string $columnName,
    ) {
        parent::__construct(sprintf('Column could not be analyzed: %s', $columnName));
    }
}
