<?php

declare(strict_types=1);

namespace SqlFixture\Schema;

use RuntimeException;

final class SchemaNotFoundException extends RuntimeException
{
    /**
     * @param list<string> $knownTables
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
