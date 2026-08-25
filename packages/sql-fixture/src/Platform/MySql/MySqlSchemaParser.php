<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql;

use Override;
use PhpMyAdmin\SqlParser\Components\DataType;
use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\SchemaParseException;
use SqlFixture\Schema\SchemaParserInterface;
use SqlFixture\Schema\TableSchema;

/**
 * Reads a MySQL CREATE TABLE statement as a table a fixture can be built for.
 *
 * MySQL's grammar is large enough that reading it by hand would be a mistake,
 * so an upstream parser does that and what is left is turning what it produced
 * into the schema a fixture is generated against.
 */
final class MySqlSchemaParser implements SchemaParserInterface
{
    /**
     * @param MySqlCreateStatement $statement Reads the parts of the parsed statement
     * @param MySqlColumnReader $columns Reads one column definition
     */
    public function __construct(
        private readonly MySqlCreateStatement $statement = new MySqlCreateStatement(),
        private readonly MySqlColumnReader $columns = new MySqlColumnReader(),
    ) {
    }

    /**
     * Reads the statement as a table.
     *
     * @param string $createTableSql CREATE TABLE statement as it was written
     *
     * @return TableSchema The table the statement declares
     *
     * @throws SchemaParseException When the text is not a CREATE TABLE, was only partly read, or declares no column
     */
    #[Override]
    public function parse(string $createTableSql): TableSchema
    {
        $parser = new Parser($createTableSql);
        if ($parser->statements === []) {
            throw SchemaParseException::invalidSql($createTableSql, 'No statements found');
        }

        $statement = $parser->statements[0];
        if (!$statement instanceof CreateStatement) {
            throw SchemaParseException::notCreateTable($createTableSql);
        }

        $this->statement->assertNothingWasLost($parser, $statement, $createTableSql);
        $tableName = $this->statement->tableName($statement, $createTableSql);
        $primaryKeys = $this->statement->primaryKeys($statement);

        if (!is_iterable($statement->fields)) {
            throw SchemaParseException::noColumns($tableName);
        }

        /** @var array<string, ColumnDefinition> $columns */
        $columns = [];
        foreach ($statement->fields as $field) {
            $name = $field->name;
            if (!is_string($name) || $name === '' || !$field->type instanceof DataType) {
                continue;
            }
            $columnName = str_replace('`', '', $name);
            $column = $this->columns->read($field, $columnName, $primaryKeys);
            if ($column !== null) {
                $columns[$columnName] = $column;
            }
        }
        if ($columns === []) {
            throw SchemaParseException::noColumns($tableName);
        }

        return new TableSchema($tableName, $columns, $primaryKeys);
    }
}
