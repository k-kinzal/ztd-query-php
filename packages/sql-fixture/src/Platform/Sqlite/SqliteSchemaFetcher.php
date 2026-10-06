<?php

declare(strict_types=1);

namespace SqlFixture\Platform\Sqlite;

use PDO;
use RuntimeException;
use SqlFixture\Schema\SchemaFetcherInterface;
use SqlFixture\Schema\TableSchema;

/**
 * Fetches table schemas from SQLite databases.
 *
 * Reads the CREATE TABLE text SQLite keeps in sqlite_schema and parses it
 * with the SQLite grammar.
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
     * @throws RuntimeException
     */
    public function fetchSchema(PDO $pdo, string $tableName): TableSchema
    {
        $createTableSql = (new Schema\CreateTableQuery())->fetchCreateTableSql($pdo, $tableName);
        if ($createTableSql === null) {
            throw new RuntimeException("Table not found: {$tableName}");
        }

        return $this->parser->parse($createTableSql);
    }
}
