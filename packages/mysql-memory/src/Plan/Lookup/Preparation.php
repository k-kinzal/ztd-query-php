<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Lookup;

use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Path\AccessPath;
use MySqlMemory\Plan\Path\Source\KeyLookup;
use MySqlMemory\Plan\Path\Transform;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Plan\QueryPlan;
use SqlSemantics\Platform\MySql\Statement\Query\Select;

/**
 * Places constant subquery preparation at the query block's versioned execution boundary.
 *
 * @visibility MySqlMemory
 */
final class Preparation
{
    /**
     * @param Planner $planner The statement planner
     */
    public function __construct(public readonly Planner $planner)
    {
    }

    /**
     * MySQL 5.6 defers membership until use, 5.7 prepares before bounds, and modern empty blocks bypass it.
     */
    public function plan(QueryPlan $plan, Select $select, Scope $scope, Scope $evaluation): QueryPlan
    {
        $conditions = $scope === $evaluation ? $scope->preparations : [...$scope->preparations, ...$evaluation->preparations];
        $early = $this->planner->compiler->settings->legacy();
        if ($conditions === [] || $this->planner->compiler->settings->release() === \SqlSemantics\Contract\GrammarRelease::MySql5651 || (!$early && $this->planner->blocks->empty($select, $scope->outer))) {
            return $plan;
        }

        return new QueryPlan(new Transform\Prepare($plan->root, $conditions, $early, $early ? null : $this->guard($plan->root)), $plan->domains, $plan->names, $plan->origins);
    }

    /**
     * Finds a constant table through the block's row-preserving and filtering operators.
     */
    public function guard(AccessPath $path): ?KeyLookup
    {
        while ($path instanceof Transform\Project || $path instanceof Transform\Filter || $path instanceof Transform\Sort || $path instanceof Transform\Limit || $path instanceof Transform\Distinct) {
            $path = $path->input;
        }

        return $path instanceof KeyLookup ? $path : null;
    }
}
