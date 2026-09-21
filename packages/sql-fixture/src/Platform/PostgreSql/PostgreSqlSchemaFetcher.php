<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql;

use PDO;
use SqlFixture\Schema\SchemaFetcherInterface;
use SqlFixture\Schema\TableSchema;
use SqlParser\PostgreSql\PostgreSqlParser;

/**
 * Fetches table schemas from PostgreSQL databases.
 *
 * PostgreSQL has no SHOW CREATE TABLE, so the schema is built from the
 * information_schema and pg_catalog rows, and each column default the
 * catalog reports is parsed with the PostgreSQL grammar.
 */
final class PostgreSqlSchemaFetcher implements SchemaFetcherInterface
{
    private Schema\CatalogSchema $catalog;

    /**
     * Loads the grammar the table name and the catalog default expressions are read with.
     */
    public function __construct(?PostgreSqlParser $parser = null)
    {
        $grammar = $parser ?? new PostgreSqlParser();
        $this->catalog = new Schema\CatalogSchema(new Schema\CatalogExpression($grammar), new Schema\QualifiedName($grammar));
    }

    /**
     * Reads the named table from the selected database connection.
     */
    public function fetchSchema(PDO $pdo, string $tableName): TableSchema
    {
        return $this->catalog->fetchSchema($pdo, $tableName);
    }
}
