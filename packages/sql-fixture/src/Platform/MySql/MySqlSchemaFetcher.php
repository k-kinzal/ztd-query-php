<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql;

use PDO;
use SqlFixture\Schema\SchemaFetcherInterface;
use SqlFixture\Schema\TableSchema;

/**
 * Fetches table schemas from MySQL databases using SHOW CREATE TABLE.
 *
 * The statement that reads the declaration and the declaration it answers are
 * both read with the grammar of the server, so neither the name written into
 * the statement nor the definition read back is taken apart as text.
 */
final class MySqlSchemaFetcher implements SchemaFetcherInterface
{
    private MySqlSchemaParser $parser;

    private Schema\CreateTableQuery $query;

    /**
     * Reads the issued statement and the declaration it answers with the grammar of one release.
     *
     * @param string|null $version The version tag of the release; null selects the default
     */
    public function __construct(?MySqlSchemaParser $parser = null, ?string $version = null)
    {
        $this->parser = $parser ?? new MySqlSchemaParser($version);
        $this->query = new Schema\CreateTableQuery(new Schema\ShowCreateTable($version));
    }

    /**
     * Reads the named table from the selected database connection.
     */
    public function fetchSchema(PDO $pdo, string $tableName): TableSchema
    {
        return $this->parser->parse($this->query->fetchCreateTableSql($pdo, $tableName));
    }
}
