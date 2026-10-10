<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Grouping;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\AggregateInOrdering;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Statement\Scalar;

/**
 * Checks whether ORDER BY introduces aggregation into an otherwise ungrouped block.
 *
 * MySQL 5.7 and later reject the first ordering expression owning an aggregate
 * unless GROUP BY, the select list or HAVING already groups that block. This rule
 * applies with ONLY_FULL_GROUP_BY disabled too. Inner aggregates owned by another
 * query do not group this block; correlated aggregates owned here do.
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html#error_er_aggregate_order_non_agg_query.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class OrderingAggregates
{
    /**
     * Answers the first ordering expression that illegally introduces a group.
     *
     * @param list<Scalar> $aggregates The resolved aggregate occurrences owned by the block
     */
    public function check(Select $select, array $aggregates, GrammarRelease $release): ?AggregateInOrdering
    {
        if ($release === GrammarRelease::MySql5651 || $select->groupBy !== null || $aggregates === []) {
            return null;
        }
        $owned = array_fill_keys(array_map(spl_object_id(...), $aggregates), true);
        $found = [];
        $first = null;
        foreach ([...$select->orderBy, ...($select->late->orderBy ?? [])] as $position => $item) {
            $inItem = $this->occurrences($item->expression, $owned);
            if ($inItem !== []) {
                $first ??= $position + 1;
                $found += $inItem;
            }
        }

        return $first !== null && array_diff_key($owned, $found) === [] ? new AggregateInOrdering($first) : null;
    }

    /**
     * Finds owned occurrences inside an expression, including nested correlated queries.
     *
     * @param array<int, true> $owned
     * @return array<int, true>
     */
    public function occurrences(Scalar $expression, array $owned): array
    {
        $pending = [$expression];
        $found = [];
        while ($pending !== []) {
            $value = array_pop($pending);
            if (is_array($value)) {
                array_push($pending, ...array_values($value));
                continue;
            }
            if (!is_object($value)) {
                continue;
            }
            $id = spl_object_id($value);
            if (isset($owned[$id])) {
                $found[$id] = true;
            }
            array_push($pending, ...array_values(get_object_vars($value)));
        }

        return $found;
    }
}
