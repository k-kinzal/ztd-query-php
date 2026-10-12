<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Grouping;

use MySqlMemory\Plan\Grouping;
use MySqlMemory\Plan\Planner;
use SqlSemantics\Platform\MySql\Rules\Query\Grouping\Matching;
use SqlSemantics\Platform\MySql\Statement\Query\Select;

/**
 * Carries a compatible output ordering into streamed input groups.
 *
 * Every ORDER BY item must match the corresponding leading GROUP BY expression.
 * A partial prefix leaves the remaining grouping keys ascending. An expression
 * outside that prefix requires a separate output sort and preserves input order.
 * Verified through concatenation and variable assignments on MySQL 8.4.
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
}
