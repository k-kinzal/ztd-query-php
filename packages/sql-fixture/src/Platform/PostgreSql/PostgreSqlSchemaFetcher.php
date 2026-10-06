<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql;

use PDO;
use SqlFixture\Schema\SchemaFetcherInterface;
use SqlFixture\Schema\TableSchema;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;

/**
 * Fetches table schemas from PostgreSQL databases.
 *
 * PostgreSQL has no SHOW CREATE TABLE, so the schema is built from the
 * information_schema and pg_catalog rows, and each column default the
 * catalog reports is analyzed as a PostgreSQL expression.
 */
final class PostgreSqlSchemaFetcher implements SchemaFetcherInterface
{
    private Schema\CatalogSchema $catalog;

    /**
     * Reads table names and column defaults with the grammar of one release.
     *
     * @param string|null $version The version tag of the release; null selects the default
     */
    public function __construct(?string $version = null)
    {
        $semantics = new Semantics(Dialect::PostgreSql, $version);
        $this->catalog = new Schema\CatalogSchema(new Schema\CatalogExpression($semantics), new Schema\QualifiedName($semantics));
    }

    /**
     * Reads the named table from the selected database connection.
     */
    public function fetchSchema(PDO $pdo, string $tableName): TableSchema
    {
        return $this->catalog->fetchSchema($pdo, $tableName);
    }
}
