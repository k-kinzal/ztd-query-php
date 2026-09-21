<?php

declare(strict_types=1);

namespace SqlFixture\Platform\Sqlite;

use SqlFixture\Schema\Exception\InvalidSqlException;
use SqlFixture\Schema\SchemaParserInterface;
use SqlFixture\Schema\TableSchema;
use SqlParser\Lexer\SourceException;
use SqlParser\Sqlite\SqliteParser;

/**
 * Reads SQLite CREATE TABLE statements into table schemas.
 *
 * The statement is parsed with the SQLite grammar, so the declared type
 * names that drive type affinity, the column constraints and the table
 * constraints are read from the syntax tree rather than from the text.
 *
 * @visibility public
 * @example Read column dimensions and a composite primary key
 *     $schema = (new \SqlFixture\Platform\Sqlite\SqliteSchemaParser())->parse('CREATE TABLE users (tenant INT, id INT, name VARCHAR(30), PRIMARY KEY (tenant, id))');
 *     $schema->primaryKeys // => ['tenant', 'id']
 *     $schema->columns['name']->length // => 30
 */
final class SqliteSchemaParser implements SchemaParserInterface
{
    private SqliteParser $parser;

    /**
     * Loads the grammar tables once for every statement the parser will read.
     */
    public function __construct(?SqliteParser $parser = null)
    {
        $this->parser = $parser ?? new SqliteParser();
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
        $columns = (new Schema\TableDefinition())->extractColumns($statement, $createTableSql, $tableName);
        $primaryKeys = (new Schema\TableDefinition())->extractPrimaryKeys($statement);

        return new TableSchema($tableName, $columns, $primaryKeys);
    }
}
