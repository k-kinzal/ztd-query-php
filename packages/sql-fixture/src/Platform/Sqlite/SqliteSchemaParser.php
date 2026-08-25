<?php

declare(strict_types=1);

namespace SqlFixture\Platform\Sqlite;

use Override;
use SqlFixture\Schema\SchemaParseException;
use SqlFixture\Schema\SchemaParserInterface;
use SqlFixture\Schema\TableSchema;

/**
 * Reads a SQLite CREATE TABLE statement as a table a fixture can be built for.
 *
 * SQLite's type system is looser than any other server's: a column may be
 * declared with no type, or with a type name SQLite has never heard of, and
 * either way it is stored under one of five affinities. Reading a statement is
 * therefore mostly a matter of taking what was written down and letting the
 * affinity rules decide later what it means.
 */
final class SqliteSchemaParser implements SchemaParserInterface
{
    /**
     * @param SqliteCreateTable $statement Reads the parts of the statement
     * @param SqliteColumnReader $columns Reads one column declaration
     */
    public function __construct(
        private readonly SqliteCreateTable $statement = new SqliteCreateTable(),
        private readonly SqliteColumnReader $columns = new SqliteColumnReader(),
    ) {
    }

    /**
     * Reads the statement as a table.
     *
     * The table-level PRIMARY KEY is read first, because a column named by one
     * is not nullable no matter what its own declaration says.
     *
     * @param string $createTableSql CREATE TABLE statement as it was written
     *
     * @return TableSchema The table the statement declares
     *
     * @throws SchemaParseException When the statement names no table, or declares no column
     */
    #[Override]
    public function parse(string $createTableSql): TableSchema
    {
        $sql = $this->statement->normalized($createTableSql);
        $tableName = $this->statement->tableName($sql);
        if ($tableName === null) {
            throw SchemaParseException::invalidSql($createTableSql, 'Could not extract table name');
        }

        $columnsBlock = $this->statement->columnsBlock($sql);
        if ($columnsBlock === null) {
            throw SchemaParseException::noColumns($tableName);
        }

        $primaryKeys = $this->statement->primaryKeys($columnsBlock);
        $columns = [];
        foreach ($this->statement->definitions($columnsBlock) as $definition) {
            if ($definition === '' || $this->statement->isTableConstraint($definition)) {
                continue;
            }
            $column = $this->columns->read($definition, $primaryKeys);
            if ($column !== null) {
                $columns[$column->name] = $column;
            }
        }
        if ($columns === []) {
            throw SchemaParseException::noColumns($tableName);
        }

        return new TableSchema($tableName, $columns, $primaryKeys);
    }
}
