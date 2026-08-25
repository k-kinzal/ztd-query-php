<?php

declare(strict_types=1);

namespace SqlFixture\Schema;

use RuntimeException;

/**
 * Reports a table nothing can describe.
 */
final class SchemaNotFoundException extends RuntimeException
{
    /**
     * Reports a table nothing can resolve.
     *
     * @param string $tableName Table that was asked for
     * @param list<string> $knownTables Tables that could have been asked for
     *
     * @return self Exception naming the table, and what was available
     */
    public static function forTable(string $tableName, array $knownTables = []): self
    {
        if ($knownTables === []) {
            return new self(sprintf('Schema not found for table: %s', $tableName));
        }

        sort($knownTables);

        return new self(sprintf(
            'Schema not found for table: %s. Known tables: %s',
            $tableName,
            implode(', ', $knownTables)
        ));
    }
}
