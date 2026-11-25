<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Aggregation;

use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\RangeIntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\SelectSql;
use SqlSemantics\Platform\MySql\Ir\TableSource;

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
            $result = $this->operand($column->value, $query->source, $renderer);
            $name = $column->name->value;
            $items[] = $result . ($name === ltrim($name, " \t\r\n\v\f") ? ' AS ' . $column->name->quoted() : '');
        }
        $sql = 'SELECT ' . implode(', ', $items) . ' FROM ' . $renderer->from($query->source);
        if ($query->where !== null) {
            $sql .= ' WHERE ' . $renderer->predicate($query->where, $query->source);
        }
        $keys = [];
        foreach ($query->keys as $key) {
            $keys[] = $this->operand($key, $query->source, $renderer);
        }

        $sql .= $keys === [] ? '' : ' GROUP BY ' . implode(', ', $keys);
        if ($query->having instanceof GroupedComparison) {
            $left = $this->operand($query->having->left, $query->source, $renderer);
            $right = $this->operand($query->having->right, $query->source, $renderer);
            $sql .= ' HAVING (' . $left . ' ' . $query->having->operator->value . ' ' . $right . ')';
        } elseif ($query->having !== null) {
            $sql .= ' HAVING ' . $renderer->expression($query->having, $query->source);
        }

        return $sql;
    }

    /**
     * Emits a checked grouping field, literal or aggregate.
     */
    public function operand(Aggregate|ColumnReference|GroupNameReference|IntegerLiteral|NullLiteral|RangeIntegerLiteral $value, TableSource $source, SelectSql $renderer): string
    {
        if ($value instanceof GroupNameReference) {
            return $value->name->quoted();
        }
        if (!$value instanceof Aggregate) {
            return $renderer->expression($value, $source);
        }
        $argument = $value->argument === null ? '*' : $renderer->expression($value->argument, $source);

        return $value->operator->value . '(' . ($value->distinct ? 'DISTINCT ' : '') . $argument . ')';
    }
}
