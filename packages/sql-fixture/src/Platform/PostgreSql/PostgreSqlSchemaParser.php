<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql;

use SqlFixture\Analysis\CreateTableOperation;
use SqlFixture\Schema\Exception\InvalidSqlException;
use SqlFixture\Schema\Exception\MissingColumnDefinitionsException;
use SqlFixture\Schema\SchemaParserInterface;
use SqlFixture\Schema\TableSchema;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable;

/**
 * Reads PostgreSQL CREATE TABLE statements into table schemas.
 *
 * The statement is analyzed with the grammar and rules of the selected
 * PostgreSQL release, so names are folded and types resolved as the server
 * resolves them, and a statement the server would refuse is rejected.
 */
final class PostgreSqlSchemaParser implements SchemaParserInterface
{
    private Semantics $semantics;

    /**
     * Loads the grammar of the release once for every statement the parser will read.
     *
     * @param string|null $version The version tag of the release; null selects the default
     */
    public function __construct(?string $version = null)
    {
        $this->semantics = new Semantics(Dialect::PostgreSql, $version);
    }

    /**
     * Parses the supplied declaration into its normalized representation.
     * @throws InvalidSqlException
     * @throws \SqlFixture\Schema\Exception\ExpectedCreateTableException
     * @throws MissingColumnDefinitionsException
     */
    public function parse(string $createTableSql): TableSchema
    {
        $operation = (new CreateTableOperation())->locate($this->semantics, $createTableSql);
        $statement = $operation->statement;
        $tableName = (new CreateTableOperation())->tableName($operation);
        if (!$statement instanceof CreateTable) {
            throw new MissingColumnDefinitionsException($tableName);
        }

        $primaryKeys = (new Schema\TableDefinition())->primaryKeys($operation, $statement);
        $columns = (new Schema\TableDefinition())->columns($statement, (new CreateTableOperation())->columns($operation), $primaryKeys);
        if ($columns === []) {
            throw new MissingColumnDefinitionsException($tableName);
        }

        return new TableSchema($tableName, $columns, $primaryKeys);
    }
}
