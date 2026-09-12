<?php

declare(strict_types=1);

namespace SqlFixture\Platform\Sqlite;

use PDO;
use SqlFixture\Schema\SchemaFetcherInterface;
use SqlFixture\Schema\TableSchema;

/**
 * Fetches table schemas from SQLite databases.
 *
 * Uses PRAGMA table_info to build the schema directly, avoiding the need
 * to parse CREATE TABLE statements for simple cases. Falls back to
 * sqlite_schema for full CREATE TABLE parsing when needed.
 */
final class SqliteSchemaFetcher implements SchemaFetcherInterface
{
    private SqliteSchemaParser $parser;

    /**
     * Initializes the collaborators and declared state for this object.
     */
    public function __construct(?SqliteSchemaParser $parser = null)
    {
        $this->parser = $parser ?? new SqliteSchemaParser();
    }

    /**
     * Reads the named table from the selected database connection.
     */
    public function fetchSchema(PDO $pdo, string $tableName): TableSchema
    {
        $createTableSql = (new Schema\CreateTableQuery())->fetchCreateTableSql($pdo, $tableName);

        if ($createTableSql !== null) {
            return $this->parser->parse($createTableSql);
        }

        return (new Schema\PragmaSchema())->fetchSchemaViaPragma($pdo, $tableName);
    }

}
