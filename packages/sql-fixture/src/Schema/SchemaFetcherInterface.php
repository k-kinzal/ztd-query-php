<?php

declare(strict_types=1);

namespace SqlFixture\Schema;

use PDO;

/**
 * Interface for fetching table schemas from a database connection.
 */
interface SchemaFetcherInterface
{
    /**
     * Fetch the schema for a table from the database.
     *
     * @param PDO $pdo Database connection
     * @param string $tableName Table name (e.g., "users" or "mydb.users")
     * @return TableSchema Parsed table schema
     * @throws SchemaParseException If the schema cannot be fetched or parsed
     */
    public function fetchSchema(PDO $pdo, string $tableName): TableSchema;
}
