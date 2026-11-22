<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Aggregation;

use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\SelectSql;

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
            $value = $field->value;
            if ($value instanceof ColumnReference) {
                $result = $renderer->expression($value, $query->source);
            } else {
                $argument = $value->argument === null ? '*' : $renderer->expression($value->argument, $query->source);
                if ($value->distinctCollation !== null) {
                    $argument = 'DISTINCT ' . $argument . ' COLLATE ' . $value->distinctCollation->value;
                }
                $result = 'count(' . $argument . ')';
            }
            $items[] = $result . ' AS ' . $field->name->quoted();
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
}
