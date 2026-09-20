<?php

declare(strict_types=1);

namespace SqlFixture\Fixture\Generation;

use RuntimeException;

/**
 * Explicit row values contradict a key inherited from a related row.
 */
final class RelationValueException extends RuntimeException
{
    /**
     * Identifies the table and column whose two fixed values disagree.
     */
    public function __construct(string $table, string|int $column)
    {
        parent::__construct(sprintf('Conflicting relation values for %s.%s. Related rows must use the same key.', $table, $column));
    }
}
