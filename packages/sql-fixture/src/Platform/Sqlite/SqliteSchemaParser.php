<?php

declare(strict_types=1);

namespace SqlFixture\Platform\Sqlite;

use SqlFixture\Analysis\CreateTableOperation;
use SqlFixture\Schema\Exception\InvalidSqlException;
use SqlFixture\Schema\Exception\MissingColumnDefinitionsException;
use SqlFixture\Schema\SchemaParserInterface;
use SqlFixture\Schema\TableSchema;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;

/**
 * Reads SQLite CREATE TABLE statements into table schemas.
 *
 * The statement is analyzed with the grammar and rules of the selected SQLite
 * release, so the declared type names that drive type affinity, the column
 * constraints and the table constraints are read from its typed model rather
 * than from the text, and a statement SQLite would refuse is rejected.
 *
 * @visibility public
 * @example Read column dimensions and a composite primary key
 *     $schema = (new \SqlFixture\Platform\Sqlite\SqliteSchemaParser())->parse('CREATE TABLE users (tenant INT, id INT, name VARCHAR(30), PRIMARY KEY (tenant, id))');
 *     $schema->primaryKeys // => ['tenant', 'id']
 *     $schema->columns['name']->length // => 30
 */
final class SqliteSchemaParser implements SchemaParserInterface
{
    private Semantics $semantics;

    /**
     * Loads the grammar of the release once for every statement the parser will read.
     *
     * @param string|null $version The version tag of the release; null selects the default
     */
    public function __construct(?string $version = null)
    {
        $this->semantics = new Semantics(Dialect::Sqlite, $version);
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
