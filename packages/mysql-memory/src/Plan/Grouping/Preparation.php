<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Grouping;

use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Plan\QueryPlan;
use SqlSemantics\Platform\MySql\Statement\Query\Select;

/**
 * Prepares constant DISTINCT aggregate arguments before any enclosing row is read.
 *
 * Empty tables and row-dependent filters still prepare their aggregate inputs.
 * Statically empty blocks suppress both their own and their nested checks. A
 * correlated subquery prepares once before the outer projection, not per row.
 * Verified through SQL and user variables on MySQL 8.4.
 *
 * @visibility MySqlMemory
 */
final class Preparation
{
    /**
     * @param Planner $planner The current statement planner
     */
    public function __construct(public readonly Planner $planner)
    {
    }

    /**
     * Carries checks outward until the enclosing block has also passed its bounds.
     */
    public function plan(QueryPlan $plan, Select $select, Scope $scope, Scope $evaluation): QueryPlan
    {
        $inputs = $scope === $evaluation ? $scope->aggregateInputs : [...$scope->aggregateInputs, ...$evaluation->aggregateInputs];
        if ($inputs === []) {
            return $plan;
        }
        $frame = new Frame($this->planner->compiler->connection->context);
        if ($this->planner->blocks->empty($select, $scope->outer, countOffsets: true, assignmentsVary: false) || $this->missing($select, $scope, $frame)) {
            $kept = $frame->context->kept;
            foreach ($inputs as $input) {
                $kept[$input] = [0];
            }
            return $plan;
        }
        if ($scope->outer !== null) {
            $scope->outer->aggregateInputs = [...$scope->outer->aggregateInputs, ...$inputs];
            return $plan;
        }
        foreach ($inputs as $input) {
            $input->enabled($frame);
        }

        return $plan;
    }

    /**
     * MySQL 9.1 emits an empty ROLLUP grand total unless preparation prunes the input.
     */
    public function emptyTotal(Select $select, Scope $scope): bool
    {
        return $this->planner->settings->release() === \SqlSemantics\Contract\GrammarRelease::MySql910
            && !$this->planner->blocks->empty($select, $scope->outer, countOffsets: true, assignmentsVary: false)
            && !$this->missing($select, $scope, new Frame($this->planner->compiler->connection->context));
    }

    /**
     * Tests whether a complete constant primary-key lookup already proves emptiness.
     */
    public function missing(Select $select, Scope $scope, Frame $frame): bool
    {
        if (!$select->from instanceof \SqlSemantics\Platform\MySql\Statement\Relation\TableReference) {
            return false;
        }
        $scan = $scope->scans[spl_object_id($select->from)] ?? null;
        if ($scan === null) {
            return false;
        }
        $path = (new \MySqlMemory\Plan\Lookup\PrimaryKeys($this->planner))->plan($select, $scan, $scope, runtime: true);
        if (!$path instanceof \MySqlMemory\Plan\Path\Source\KeyLookup) {
            return false;
        }
        $variables = $frame->context->variables;
        $rows = $variables->instance->transactions->of($variables->connection)?->access->rows($scan->table) ?? $scan->table->data->rows;
        $values = array_map(static fn (array $binding): int|float|string|null => $binding[1]->evaluate($frame), $path->bindings);
        foreach ($rows as $row) {
            $matches = true;
            foreach ($path->bindings as $index => [$position, , $comparator]) {
                $matches = $matches && $row[$position] !== null && $values[$index] !== null && $comparator->compare($row[$position], $values[$index], $frame->context) === 0;
            }
            if ($matches) {
                return false;
            }
        }

        return true;
    }
}
