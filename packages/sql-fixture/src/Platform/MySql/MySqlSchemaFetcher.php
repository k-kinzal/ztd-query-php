<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql;

use PDO;
use SqlFixture\Schema\SchemaFetcherInterface;
use SqlFixture\Schema\TableSchema;
use SqlParser\MySql\MySqlParser;

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
     * Shares one grammar between the statement that is issued and the declaration it answers.
     */
    public function __construct(?MySqlSchemaParser $parser = null, MySqlParser $grammar = new MySqlParser())
    {
        $this->parser = $parser ?? new MySqlSchemaParser($grammar);
        $this->query = new Schema\CreateTableQuery(new Schema\ShowCreateTable($grammar));
    }

    /**
     * Reads the named table from the selected database connection.
     */
    public function fetchSchema(PDO $pdo, string $tableName): TableSchema
    {
        return $this->parser->parse($this->query->fetchCreateTableSql($pdo, $tableName));
    }
}
