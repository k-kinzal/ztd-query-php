<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql;

use PDO;
use SqlFixture\Schema\SchemaFetcherInterface;
use SqlFixture\Schema\TableSchema;

/**
 * Fetches table schemas from MySQL databases using SHOW CREATE TABLE.
 */
final class MySqlSchemaFetcher implements SchemaFetcherInterface
{
    private MySqlSchemaParser $parser;

    /**
     * Initializes the collaborators and declared state for this object.
     */
    public function __construct(?MySqlSchemaParser $parser = null)
    {
        $this->parser = $parser ?? new MySqlSchemaParser();
    }

    /**
     * Reads the named table from the selected database connection.
     */
    public function fetchSchema(PDO $pdo, string $tableName): TableSchema
    {
        $createTableSql = (new Schema\CreateTableQuery())->fetchCreateTableSql($pdo, $tableName);
        return $this->parser->parse($createTableSql);
    }




}
