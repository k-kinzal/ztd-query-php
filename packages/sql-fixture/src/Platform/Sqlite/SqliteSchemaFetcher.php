<?php

declare(strict_types=1);

namespace SqlFixture\Platform\Sqlite;

use Override;
use PDO;
use RuntimeException;
use SqlFixture\Schema\SchemaFetcherInterface;
use SqlFixture\Schema\SchemaParseException;
use SqlFixture\Schema\TableSchema;

/**
 * Describes a live SQLite table.
 *
 * SQLite keeps the statement each table was created with, and reading that
 * back through the same parser a `.sql` file goes through is the best answer
 * available: it carries everything the author wrote. Only where no statement
 * was recorded does the pragma have to answer instead, and it answers with
 * less — SQLite has already reduced the declaration to what it enforces.
 */
final class SqliteSchemaFetcher implements SchemaFetcherInterface
{
    /**
     * @param SqliteSchemaParser $parser Reads a declaration as a table
     * @param SqliteCatalog $catalog Reads a table out of SQLite's catalog
     */
    public function __construct(
        private readonly SqliteSchemaParser $parser = new SqliteSchemaParser(),
        private readonly SqliteCatalog $catalog = new SqliteCatalog(),
    ) {
    }

    /**
     * Describes the table as the connection currently holds it.
     *
     * @param PDO $pdo Connection to read through
     * @param string $tableName Table to describe
     *
     * @return TableSchema The table
     *
     * @throws RuntimeException When the connection knows no such table
     * @throws SchemaParseException When the recorded statement cannot be read back
     */
    #[Override]
    public function fetchSchema(PDO $pdo, string $tableName): TableSchema
    {
        $createTableSql = $this->catalog->createTableSqlOf($pdo, $tableName);

        return $createTableSql !== null
            ? $this->parser->parse($createTableSql)
            : $this->catalog->tableInfo($pdo, $tableName);
    }
}
