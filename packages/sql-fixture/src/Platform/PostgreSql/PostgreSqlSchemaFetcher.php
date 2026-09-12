<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql;

use PDO;
use SqlFixture\Schema\SchemaFetcherInterface;
use SqlFixture\Schema\TableSchema;

/**
 * Fetches table schemas from PostgreSQL databases.
 *
 * Uses information_schema to query column definitions, since PostgreSQL
 * does not have a SHOW CREATE TABLE equivalent.
 */
final class PostgreSqlSchemaFetcher implements SchemaFetcherInterface
{
    private PostgreSqlSchemaParser $parser;

    /**
     * Initializes the collaborators and declared state for this object.
     */
    public function __construct(?PostgreSqlSchemaParser $parser = null)
    {
        $this->parser = $parser ?? new PostgreSqlSchemaParser();
    }

    /**
     * Reads the named table from the selected database connection.
     */
    public function fetchSchema(PDO $pdo, string $tableName): TableSchema
    {
        $createTableSql = (new Schema\CatalogDdl())->reconstructCreateTable($pdo, $tableName);

        if ($createTableSql !== null) {
            return $this->parser->parse($createTableSql);
        }

        return (new Schema\CatalogSchema())->fetchSchemaFromInformationSchema($pdo, $tableName);
    }

}
