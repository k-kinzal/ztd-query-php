<?php

declare(strict_types=1);

namespace SqlFixture\Fixture\Exception;

use SqlFixture\InvalidOverrideException;
use SqlFixture\Schema\TableSchema;

/**
 * An override supplies a value computed by the database.
 */
final class GeneratedColumnOverrideException extends InvalidOverrideException
{
    /**
     * An override supplies a value computed by the database.
     */
    public function __construct(
        public readonly string $column,
        public readonly TableSchema $schema,
    ) {
        parent::__construct(sprintf(
            'Cannot override %s.%s: the database computes it, so a value written here '
            . 'would be rejected on insert.',
            $schema->tableName,
            $column
        ));
    }
}
