<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Grouping;

use MySqlMemory\Plan\Grouping;
use MySqlMemory\Plan\Planner;
use SqlSemantics\Platform\MySql\Rules\Query\Grouping\Matching;
use SqlSemantics\Platform\MySql\Statement\Query\Select;

/**
 * Resolves group ordering before output sorting and duplicate removal.
 *
 * Every ORDER BY item must match the corresponding leading GROUP BY expression.
 * A partial prefix leaves the remaining grouping keys ascending. An expression
 * outside that prefix requires a separate output sort and preserves input order.
 * Verified through concatenation and variable assignments on MySQL 8.4.
 * Legacy DISTINCT over hashed groups preserves their arrival order when it stores
 * new duplicate keys or an explicit output order replaces implicit group ordering.
 *
 * @visibility MySqlMemory
 */
final class Ordering
{
    /**
     * @param Planner $planner The current statement planner
     */
    public function __construct(public readonly Planner $planner)
    {
    }

    /**
     * Answers the descending flags of a compatible grouping prefix.
     *
     * @return list<bool>
     */
    public function directions(Select $select): array
    {
        if ($select->groupBy === null || $this->planner->settings->legacy()) {
            return [];
        }
        $grouping = new Grouping($this->planner);
        $matching = new Matching();
        $directions = [];
        foreach ([...$select->orderBy, ...($select->late->orderBy ?? [])] as $position => $item) {
            $group = $select->groupBy->items[$position] ?? null;
            if ($group === null || !$matching->same($grouping->target($group->expression), $grouping->target($item->expression), $this->planner->compiler->facts)) {
                return [];
            }
            $directions[] = $item->direction?->value === 'DESC';
        }

        return $directions;
    }

    /**
     * Preserves arrival order for legacy hashed groups before DISTINCT stores its keys.
     * Redundant DISTINCT retains implicit group ordering unless an explicit order replaces it.
     * Streaming tuple aggregates, indexes and ROLLUP retain their sorted input.
     * Verified through SQL on MySQL 5.6 and 5.7 in Testcontainers.
     */
    public function arrival(Select $select, bool $hashed): bool
    {
        if (!$this->planner->settings->legacy() || !$hashed || $select->groupBy === null || $select->groupBy->modifier !== null || !in_array(\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::Distinct, $select->options, true)) {
            return false;
        }
        $expressions = [];
        foreach ($select->items as $item) {
            if (!$item instanceof \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression) {
                return false;
            }
            $expressions[] = $item->expression;
        }
        return $select->orderBy !== [] || ($select->late->orderBy ?? []) !== [] || !(new \SqlSemantics\Platform\MySql\Rules\Query\Grouping\DistinctOrdering())->redundant($select, $expressions, $this->planner->compiler->facts);
    }
}
