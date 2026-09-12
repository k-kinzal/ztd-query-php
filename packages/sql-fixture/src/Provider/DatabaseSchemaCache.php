<?php

declare(strict_types=1);

namespace SqlFixture\Provider;

use PDO;
use SqlFixture\Schema\SchemaFetcherInterface;
use SqlFixture\Schema\TableSchema;

/**
 * Caches reflected table schemas for one connection.
 *
 * @visibility root
 */
final class DatabaseSchemaCache
{
    /**
     * @var array<string, TableSchema>
     */
    private array $schemaCache = [];

    /**
     * Reflects missing tables through the selected dialect fetcher.
     */
    public function __construct(private readonly PDO $connection, private readonly SchemaFetcherInterface $schemaFetcher)
    {
    }

    /**
     * Get or fetch schema for a table.
     */
    public function getSchema(string $tableName): TableSchema
    {
        $normalizedName = str_replace(['`', '"'], '', $tableName);

        if (!isset($this->schemaCache[$normalizedName])) {
            $this->schemaCache[$normalizedName] = $this->schemaFetcher->fetchSchema(
                $this->connection,
                $tableName
            );
        }

        return $this->schemaCache[$normalizedName];
    }

    /**
     * Invalidates reflected schemas after database DDL changes.
     */
    public function clear(): void
    {
        $this->schemaCache = [];
    }
}
