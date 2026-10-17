<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql;

use SqlFixture\Schema\Exception\InvalidSqlException;
use SqlFixture\Schema\SchemaParserInterface;
use SqlFixture\Schema\TableSchema;
use SqlParser\Lexer\SourceException;
use SqlParser\MySql\MySqlParser;

/**
 * Reads MySQL CREATE TABLE statements into table schemas.
 *
 * The statement is parsed with the grammar of a MySQL 8 release, so the
 * schema is read from the syntax tree rather than from the statement text.
 */
final class MySqlSchemaParser implements SchemaParserInterface
{
    private MySqlParser $parser;

    /**
     * Loads the grammar tables once for every statement the parser will read.
     */
    public function __construct(?MySqlParser $parser = null)
    {
        $this->parser = $parser ?? new MySqlParser();
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
