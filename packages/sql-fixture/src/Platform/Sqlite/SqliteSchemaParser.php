<?php

declare(strict_types=1);

namespace SqlFixture\Platform\Sqlite;

use SqlFixture\Schema\SchemaParserInterface;
use SqlFixture\Schema\TableSchema;

/**
 * Simple regex-based parser for SQLite CREATE TABLE statements.
 *
 * SQLite has a simpler type system based on "type affinity" rather than
 * strict types like MySQL. This parser handles the basic SQLite column
 * definitions and extracts type affinity information.
 *
 * @visibility public
 * @example Read column dimensions and a composite primary key
 *     $schema = (new \SqlFixture\Platform\Sqlite\SqliteSchemaParser())->parse('CREATE TABLE users (tenant INT, id INT, name VARCHAR(30), PRIMARY KEY (tenant, id))');
 *     $schema->primaryKeys // => ['tenant', 'id']
 *     $schema->columns['name']->length // => 30
 */
final class SqliteSchemaParser implements SchemaParserInterface
{
    /**
     * Parses the supplied declaration into its normalized representation.
     * @throws \SqlFixture\Schema\Exception\InvalidSqlException
     * @throws \SqlFixture\Schema\Exception\MissingColumnDefinitionsException
     */
    public function parse(string $createTableSql): TableSchema
    {
        $sql = (new Schema\TableSyntax())->normalizeSql($createTableSql);

        $tableName = (new Schema\TableSyntax())->extractTableName($sql);
        if ($tableName === null) {
            throw new \SqlFixture\Schema\Exception\InvalidSqlException($createTableSql, 'Could not extract table name');
        }

        $columnsBlock = (new Schema\TableSyntax())->extractColumnsBlock($sql);
        if ($columnsBlock === null) {
            throw new \SqlFixture\Schema\Exception\MissingColumnDefinitionsException($tableName);
        }

        $primaryKeys = (new Schema\TableSyntax())->extractTablePrimaryKeys($columnsBlock);
        $columns = (new Schema\DefinitionList())->parseColumns($columnsBlock, $tableName, $primaryKeys);

        if ($columns === []) {
            throw new \SqlFixture\Schema\Exception\MissingColumnDefinitionsException($tableName);
        }

        return new TableSchema($tableName, $columns, $primaryKeys);
    }

}
