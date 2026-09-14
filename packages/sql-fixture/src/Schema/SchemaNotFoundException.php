<?php

declare(strict_types=1);

namespace SqlFixture\Schema;

use RuntimeException;

/**
 * A requested table has no registered schema.
 */
final class SchemaNotFoundException extends RuntimeException
{
    /**
     * @var list<string>
     */
    public readonly array $knownTables;

    /**
     * Retains the missing table and available alternatives for callers.
     * @param list<string> $knownTables
     */
    public function __construct(public readonly string $tableName, array $knownTables = [])
    {
        sort($knownTables);
        $this->knownTables = $knownTables;
        parent::__construct(
            $knownTables === []
            ? sprintf('Schema not found for table: %s', $tableName)
            : sprintf('Schema not found for table: %s. Known tables: %s', $tableName, implode(', ', $knownTables))
        );
    }
}
