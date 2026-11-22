<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Aggregation;

use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\SelectSql;

/**
 * Emits the checked native grouping roles in query clause order.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class GroupedSql
{
    /**
     * Emits the group over the same scoped input as its filter.
     */
    public function render(GroupedSelect $query): string
    {
        $renderer = new SelectSql();
        $items = [];
        foreach ($query->columns as $column) {
            $value = $column->value;
            if ($value instanceof ColumnReference) {
                $result = $renderer->expression($value, $query->source);
            } else {
                $argument = $value->argument === null ? '*' : $renderer->expression($value->argument, $query->source);
                $result = $value->operator->value . '(' . ($value->distinct ? 'DISTINCT ' : '') . $argument . ')';
            }
            $items[] = $result . ' AS ' . $column->name->quoted();
        }
        $sql = 'SELECT ' . implode(', ', $items) . ' FROM ' . $renderer->from($query->source);
        if ($query->where !== null) {
            $sql .= ' WHERE ' . $renderer->predicate($query->where, $query->source);
        }
        $keys = [];
        foreach ($query->keys as $key) {
            $keys[] = $renderer->expression($key, $query->source);
        }

        return $sql . ($keys === [] ? '' : ' GROUP BY ' . implode(', ', $keys));
    }
}
