<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Ordering;

use SqlSemantics\Platform\PostgreSql\Ir\Select;

/**
 * Emits the checked sort keys and row bounds in SQL clause order.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class ResultSql
{
    /**
     * Returns the SQL suffix of the checked query result.
     */
    public function render(Select $select): string
    {
        $terms = [];
        foreach ($select->orderBy as $key) {
            $terms[] = $key->position . ($key->descending ? ' DESC' : ' ASC');
            $terms[count($terms) - 1] .= $key->nullsFirst ? ' NULLS FIRST' : ' NULLS LAST';
        }
        $sql = $terms === [] ? '' : ' ORDER BY ' . implode(', ', $terms);
        if ($select->limit?->count !== null) {
            $sql .= ' LIMIT ' . $select->limit->count;
        }
        if ($select->limit !== null && $select->limit->offset > 0) {
            $sql .= ' OFFSET ' . $select->limit->offset;
        }

        return $sql;
    }
}
