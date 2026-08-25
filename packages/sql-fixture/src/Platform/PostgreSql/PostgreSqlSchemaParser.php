<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql;

use Override;
use SqlFixture\Schema\SchemaParseException;
use SqlFixture\Schema\SchemaParserInterface;
use SqlFixture\Schema\TableSchema;

/**
 * Reads a PostgreSQL CREATE TABLE statement as a table a fixture can be built for.
 *
 * PostgreSQL declares tables under a schema, spells several of its types as
 * more than one word, offers SERIAL as a shorthand for a column the server
 * fills in, and writes an array type by suffixing the member type. All of
 * those change how a declaration is read rather than what a table is, so they
 * are answered by the readers this composes.
 */
final class PostgreSqlSchemaParser implements SchemaParserInterface
{
    /**
     * @param PostgreSqlCreateTable $statement Reads the parts of the statement
     * @param PostgreSqlColumnReader $columns Reads one column declaration
     */
    public function __construct(
        private readonly PostgreSqlCreateTable $statement = new PostgreSqlCreateTable(),
        private readonly PostgreSqlColumnReader $columns = new PostgreSqlColumnReader(),
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
