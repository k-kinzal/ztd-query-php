<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Window;

use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Evaluation\Window\Analytic;
use MySqlMemory\Evaluation\Window\OutputRead;
use MySqlMemory\Plan\ConstantTables;
use MySqlMemory\Plan\Path\AccessPath;
use MySqlMemory\Plan\Path\Transform\Limit;
use MySqlMemory\Plan\Path\Transform\Project;
use MySqlMemory\Plan\Planner;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Platform\MySql\Statement\Relation\Dual;

/**
 * Evaluates windows directly when the FROM clause supplies a constant row.
 *
 * Such windows read their arguments at projection time, including each ROLLUP
 * output. Frame boundaries do not change their values, but are still checked.
 * A LIMIT discards input before evaluating these projections. Verified through
 * values, warnings and FOUND_ROWS on MySQL 8.0, 8.4 and 9.1.
 *
 * @visibility MySqlMemory
 */
final class Direct
{
    /**
     * @param Planner $planner The planner of the query block
     */
    public function __construct(public readonly Planner $planner)
    {
    }

    /**
     * Answers whether the source is absent, DUAL, or entirely constant tables.
     */
    public function constant(Select $select): bool
    {
        return $select->from === null || $select->from instanceof Dual || (new ConstantTables($this->planner->compiler->facts))->joined($select) === 0;
    }

    /**
     * Binds calls to independent direct-read slots after checking their frames.
     *
     * @param list<Specification> $windows The resolved windows
     * @param array<int, Analytic> $analytics Compiled calls keyed by semantic identity
     */
    public function bind(Select $select, Scope $scope, array $windows, array $analytics): void
    {
        $compiler = $this->planner->compiler;
        $position = -1;
        foreach ($windows as $window) {
            $order = array_map(static fn (OrderItem $item): array => [$compiler->compile($item->expression, $scope), $item->direction?->value === 'DESC'], $window->order);
            (new Frames($this->planner))->compile($window, $order, $scope);
            foreach ($window->calls as $call) {
                $scope->bind($call, new OutputRead($analytics[spl_object_id($call)], $position--, true, $select->from !== null && !$select->from instanceof Dual));
            }
        }
    }

    /**
     * Moves bounds before direct projection when sorting or DISTINCT needs no output values.
     */
    public function bounded(AccessPath $path, Select $select): AccessPath
    {
        if (!$path instanceof Limit || !$path->input instanceof Project || !$this->constant($select) || (new Windowing($this->planner))->calls($select) === []) {
            return $path;
        }
        $project = $path->input;
        $input = new Limit($project->input, $path->count, $path->offset, true, in_array(SelectOption::CalcFoundRows, $select->options, true));

        return new Project($input, $project->expressions);
    }
}
