<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql;

use Override;
use PDO;
use RuntimeException;
use SqlFixture\Schema\SchemaFetcherInterface;
use SqlFixture\Schema\SchemaParseException;
use SqlFixture\Schema\TableSchema;

/**
 * Describes a live MySQL table.
 *
 * MySQL hands back the statement the table was created with, so a live table
 * and a table read from a `.sql` file arrive at the same reader and are
 * understood the same way.
 */
final class MySqlSchemaFetcher implements SchemaFetcherInterface
{
    /**
     * @param MySqlSchemaParser $parser Reads a declaration as a table
     * @param MySqlCatalog $catalog Reads a table out of MySQL's catalog
     */
    public function __construct(
        private readonly MySqlSchemaParser $parser = new MySqlSchemaParser(),
        private readonly MySqlCatalog $catalog = new MySqlCatalog(),
    ) {
    }

    /**
     * Describes the table as the server currently holds it.
     *
     * @param PDO $pdo Connection to read through
     * @param string $tableName Table to describe, optionally database-qualified
     *
     * @return TableSchema The table
     *
     * @throws RuntimeException When the connection knows no such table
     * @throws SchemaParseException When the recorded statement cannot be read back
     */
    #[Override]
    public function fetchSchema(PDO $pdo, string $tableName): TableSchema
    {
        return $this->parser->parse($this->catalog->createTableSqlOf($pdo, $tableName));
    }
}
