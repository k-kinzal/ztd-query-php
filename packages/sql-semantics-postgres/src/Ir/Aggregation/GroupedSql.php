<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Aggregation;

use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Ordering\ResultSql;
use SqlSemantics\Platform\PostgreSql\Ir\SelectSql;
use SqlSemantics\Platform\PostgreSql\Ir\TableSource;

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
            $items[] = $this->operand($column->value, $query->source, $renderer) . ' AS ' . $column->name->quoted();
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
        if ($query->having instanceof GroupedComparison) {
            $left = $this->operand($query->having->left, $query->source, $renderer);
            $right = $this->operand($query->having->right, $query->source, $renderer);
            $sql .= ' HAVING (' . $left . ' OPERATOR(pg_catalog.' . $query->having->operator->value . ') ' . $right . ')';
        } elseif ($query->having !== null) {
            $sql .= ' HAVING ' . $renderer->expression($query->having, $query->source);
        }

        return $sql . (new ResultSql())->render($query, $renderer);
    }

    /**
     * Emits an operand from a group result or predicate.
     */
    public function operand(Aggregate|ColumnReference|IntegerLiteral|WideIntegerLiteral $value, TableSource $source, SelectSql $renderer): string
    {
        if (!$value instanceof Aggregate) {
            return $renderer->expression($value, $source);
        }
        $argument = $value->argument === null ? '*' : $renderer->expression($value->argument, $source);

        return 'pg_catalog.' . $value->operator->value . '(' . ($value->distinct ? 'DISTINCT ' : '') . $argument . ')';
    }
}
