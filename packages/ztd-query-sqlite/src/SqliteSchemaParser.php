<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Sqlite;

use ZtdQuery\Platform\SchemaParser;
use ZtdQuery\Schema\TableDefinition;

/**
 * SQLite implementation of SchemaParser.
 *
 * Parses CREATE TABLE statements while preserving nested SQL expressions.
 */
final class SqliteSchemaParser implements SchemaParser
{
    /**
     * {@inheritDoc}
     *
     * @visibility public
     * @example Reflect declared columns and primary keys from SQLite DDL
     *     $schema = (new \ZtdQuery\Platform\Sqlite\SqliteSchemaParser())->parse('CREATE TABLE users(id INTEGER PRIMARY KEY, name TEXT)');
     *     $schema?->columns // => ['id', 'name']
     *     $schema?->primaryKeys // => ['id']
     */
    public function parse(string $createTableSql): ?TableDefinition
    {
        $trimmed = trim($createTableSql);

        $body = (new Schema\Create\TableBodyParser())->tableBody($trimmed);
        if ($body === null) {
            return (new Schema\Create\VirtualTableParser())->parseFts5VirtualTable($trimmed);
        }

        $builder = new Schema\Create\TableDefinitionBuilder();
        foreach ((new Schema\Create\TableBodyParser())->splitColumnDefinitions($body) as $definition) {
            $builder->addDefinition($definition);
        }

        return $builder->build($createTableSql);
    }

}
