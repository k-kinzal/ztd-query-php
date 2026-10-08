<?php

declare(strict_types=1);

namespace MySqlMemory\System;

use MySqlMemory\Dictionary\Schema;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Dictionary\View;
use MySqlMemory\Plan\Views;
use SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemTable;

/**
 * The tables and views INFORMATION_SCHEMA lists: those of each database in name order, each database's in name order.
 *
 * Temporary tables are not listed. The system tables of the release are listed in their
 * databases with the tables created there.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/information-schema-tables-table.html.
 *
 * @visibility MySqlMemory
 */
final class Listed
{
    /**
     * Answers the databases in name order; in 5.6 and 5.7 INFORMATION_SCHEMA first.
     *
     * In creation order, mysql comes first, then INFORMATION_SCHEMA and the other system
     * databases, then the databases in the order they were created.
     *
     * @param bool $created Whether to answer them in creation order instead
     *
     * @return list<Schema>
     */
    public static function schemas(Reading $reading, bool $created = false): array
    {
        $schemas = $reading->instance->dictionary->schemas;
        if ($created) {
            $mysql = isset($schemas['mysql']) ? ['mysql' => $schemas['mysql']] : [];

            return array_values($mysql + $schemas);
        }
        ksort($schemas, SORT_STRING);
        $ordered = array_values($schemas);
        if (!$reading->dictionary()) {
            usort($ordered, static fn (Schema $left, Schema $right): int => [$left->name !== 'information_schema', $left->name] <=> [$right->name !== 'information_schema', $right->name]);
        }

        return $ordered;
    }

    /**
     * Answers the tables, views and system tables of a database, by name in name order.
     *
     * @param bool $created Whether to answer the tables in the order they were created instead
     *
     * @return array<string, StoredTable|View|SystemTable>
     */
    public static function of(Schema $schema, Reading $reading, bool $created = false): array
    {
        $objects = [];
        foreach ($reading->instance->dictionary->system?->catalog->tables ?? [] as $table) {
            if ($table->schema === $schema->name) {
                $objects[$table->name] = $table;
            }
        }
        foreach ($schema->tables as $name => $table) {
            if (!$table->definition->temporary) {
                $objects[$name] = $table;
            }
        }
        foreach ($schema->views as $name => $view) {
            $objects[$name] = $view;
        }
        if (!$created) {
            ksort($objects, SORT_STRING);
        }

        return $objects;
    }

    /**
     * Answers a table of no rows with the columns of a view, as INFORMATION_SCHEMA describes them.
     */
    public static function stored(View $view, Reading $reading): StoredTable
    {
        return Views::stored($view, $reading->instance->dictionary);
    }
}
