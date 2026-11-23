<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Aggregation;

use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\Ordering\ResultSql;
use SqlSemantics\Platform\PostgreSql\Ir\SelectSql;

/**
 * Emits the checked native grouping roles in query clause order.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
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
                $result = 'pg_catalog.' . $value->operator->value . '(' . ($value->distinct ? 'DISTINCT ' : '') . $argument . ')';
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

        $sql .= $keys === [] ? '' : ' GROUP BY ' . implode(', ', $keys);

        return $sql . (new ResultSql())->render($query, $renderer);
    }
}
