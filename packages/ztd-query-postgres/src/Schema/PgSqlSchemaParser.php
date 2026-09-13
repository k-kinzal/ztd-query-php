<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres;

use ZtdQuery\Platform\SchemaParser;
use ZtdQuery\Schema\TableDefinition;

/**
 * PostgreSQL implementation of SchemaParser.
 *
 * Parses CREATE TABLE statements into structured schema metadata.
 */
final class PgSqlSchemaParser implements SchemaParser
{
    /**
     * {@inheritDoc}
     *
     * @visibility public
     * @example Read columns and the primary key from a table definition
     *     $definition = (new \ZtdQuery\Platform\Postgres\PgSqlSchemaParser())->parse('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
     *     $definition?->columns // => ['id', 'name']
     *     $definition?->primaryKeys // => ['id']
     */
    public function parse(string $createTableSql): ?TableDefinition
    {
        $body = (new Schema\Definition\TableBody())->tableBody($createTableSql);
        if ($body === null) {
            return null;
        }

        $fields = new Schema\Definition\TableFields();
        $foreignKeys = (new PgSqlForeignKeyDefinitionParser())->parseCreateTable($createTableSql);
        foreach ((new Schema\Definition\TableBody())->splitTableBody($body) as $entry) {
            $fields->appendEntry($entry);
        }
        return $fields->definition($createTableSql, $foreignKeys);
    }
}
