<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql;

use SqlFixture\Schema\Exception\InvalidSqlException;
use SqlFixture\Schema\SchemaParserInterface;
use SqlFixture\Schema\TableSchema;
use SqlParser\Lexer\SourceException;
use SqlParser\PostgreSql\PostgreSqlParser;

/**
 * Reads PostgreSQL CREATE TABLE statements into table schemas.
 *
 * The statement is parsed with the PostgreSQL grammar, so SERIAL columns,
 * array types, multi-word type names and table constraints are read from
 * the syntax tree rather than from the statement text.
 */
final class PostgreSqlSchemaParser implements SchemaParserInterface
{
    private PostgreSqlParser $parser;

    /**
     * Loads the grammar tables once for every statement the parser will read.
     */
    public function __construct(?PostgreSqlParser $parser = null)
    {
        $this->parser = $parser ?? new PostgreSqlParser();
    }

    /**
     * Parses the supplied declaration into its normalized representation.
     * @throws InvalidSqlException
     * @throws \SqlFixture\Schema\Exception\ExpectedCreateTableException
     * @throws \SqlFixture\Schema\Exception\MissingColumnDefinitionsException
     */
    public function parse(string $createTableSql): TableSchema
    {
        try {
            $tree = $this->parser->parse($createTableSql);
        } catch (SourceException $exception) {
            throw new InvalidSqlException($createTableSql, $exception->getMessage(), $exception);
        }

        $statement = (new Schema\CreateTableStatement())->locate($tree, $createTableSql);
        $tableName = (new Schema\TableDefinition())->extractTableName($statement, $createTableSql);
        $columns = (new Schema\TableDefinition())->extractColumns($statement, $tableName);
        $primaryKeys = (new Schema\TableDefinition())->extractPrimaryKeys($statement);

        return new TableSchema($tableName, $columns, $primaryKeys);
    }
}
