<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Ordering;

use SqlSemantics\Platform\PostgreSql\Ir\Aggregation\GroupedSelect;
use SqlSemantics\Platform\PostgreSql\Ir\Select;
use SqlSemantics\Platform\PostgreSql\Ir\SelectSql;

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
    public function render(Select|GroupedSelect $select, SelectSql $sqlPrinter): string
    {
        $terms = [];
        foreach ($select->orderBy as $key) {
            $target = is_int($key->target) ? (string) $key->target : $sqlPrinter->expression($key->target, $select->source);
            $terms[] = $target . ($key->descending ? ' DESC' : ' ASC');
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
