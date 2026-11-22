<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Aggregation;

use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\SelectSql;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;

/**
 * Emits the closed SQLite grouped-query roles.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class GroupedSql
{
    /**
     * Emits ordered columns and each explicit effective collation.
     */
    public function render(GroupedSelect $query): string
    {
        $renderer = new SelectSql();
        $items = [];
        foreach ($query->columns as $field) {
            $items[] = $this->field($field->value, $query->source, $renderer) . ' AS ' . $field->name->quoted();
        }
        $sql = 'SELECT ' . implode(', ', $items);
        if ($query->source !== null) {
            $sql .= ' FROM ' . $renderer->source($query->source, 0);
        }
        if ($query->where !== null) {
            $sql .= ' WHERE ' . $renderer->expression($query->where, $query->source);
        }
        $keys = [];
        foreach ($query->keys as $key) {
            $keys[] = $renderer->expression($key->column, $query->source) . ' COLLATE ' . $key->collation->value;
        }

        return $sql . ($keys === [] ? '' : ' GROUP BY ' . implode(', ', $keys));
    }

    private function field(ColumnReference|Count $value, ?TableSource $source, SelectSql $renderer): string
    {
        if ($value instanceof ColumnReference) {
            return $renderer->expression($value, $source);
        }
        if ($value->argument === null) {
            return 'count(*)';
        }
        $argument = $renderer->expression($value->argument, $source);
        if ($value->distinctCollation !== null) {
            $argument = 'DISTINCT ' . $argument . ' COLLATE ' . $value->distinctCollation->value;
        }

        return 'count(' . $argument . ')';
    }
}
