<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Projection;

use MySqlMemory\Evaluation\Compile\Constancy;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Plan\Grouping;
use MySqlMemory\Plan\Path\AccessPath;
use MySqlMemory\Plan\Path\Transform\Limit;
use MySqlMemory\Plan\Path\Transform\Project;
use MySqlMemory\Plan\Path\Transform\Sort;
use MySqlMemory\Plan\Planner;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableAssignment;
use SqlSemantics\Statement\Scalar;

/**
 * Evaluates direct select expressions when their rows pass sorting and bounds.
 *
 * Sorting ordinary input keys does not evaluate the whole select list. A sorting
 * expression with per-read state requires its projected values first. An implicit
 * aggregate supplies at most one row, so its ORDER BY needs no key evaluation.
 * Verified through SQL and PDO on MySQL 8.0, 8.4 and 9.1.
 *
 * @visibility MySqlMemory
 */
final class Emission
{
    /**
     * @param Planner $planner The statement planner
     */
    public function __construct(public readonly Planner $planner)
    {
    }

    /**
     * Defers projection for ungrouped blocks without stored window or DISTINCT fields.
     */
    public function plan(AccessPath $path, Select $select, bool $storedWindows): AccessPath
    {
        if ($storedWindows || $select->groupBy !== null || in_array(SelectOption::Distinct, $select->options, true)) {
            return $path;
        }
        $single = (new Grouping($this->planner))->collect($select) !== [];
        if (!$single && !$this->sortable($select)) {
            return $path;
        }

        return $this->bounded($path, count($this->planner->blocks->fields($select)), $single);
    }

    /**
     * Answers whether sorting can read keys without first storing the whole select list.
     */
    public function sortable(Select $select): bool
    {
        foreach ([...$select->orderBy, ...($select->late === null ? [] : $select->late->orderBy)] as $item) {
            if (!$this->independent($item->expression)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Follows output aliases and detects values that change between reads of the same row.
     */
    public function independent(Scalar $expression): bool
    {
        $target = (new Grouping($this->planner))->target($expression);
        $targets = array_map(static fn (VariableAssignment $assignment): UserVariable => $assignment->target, (new Walker())->find($target, VariableAssignment::class, false));
        foreach ((new Walker())->find($target, Scalar::class, false) as $node) {
            $resolved = (new Grouping($this->planner))->target($node);
            if ($resolved !== $node && !$this->independent($resolved)) {
                return false;
            }
            if (!$node instanceof VariableAssignment && !in_array($node, $targets, true) && Constancy::uncacheable($node) && (!$node instanceof UserVariable || !$this->planner->compiler->constancy($node)->constant())) {
                return false;
            }
        }

        return true;
    }

    /**
     * Keeps projected expressions outside each bound while preserving consumed-row counts.
     *
     * @param int $fields The number of visible output fields
     * @param bool $single Whether implicit aggregation supplies at most one row
     */
    public function bounded(AccessPath $path, int $fields, bool $single): AccessPath
    {
        if ($path instanceof Sort && $path->input instanceof Project) {
            return $single ? $path->input : $this->sorted($path, $path->input, $fields);
        }
        if (!$path instanceof Limit) {
            return $path;
        }
        $input = $this->bounded($path->input, $fields, $single);
        if (!$input instanceof Project) {
            return $path;
        }
        $limited = new Limit($input->input, $path->count, $path->offset, $path->countFound, $path->exhaust, $path->initialize);

        return new Project($limited, $input->expressions, $input->append, $input->retainAt, $input->publish, $input->order, $input->initial);
    }

    /**
     * Sorts captured keys beside the input row and evaluates visible expressions afterwards.
     *
     * @param int $fields The number of visible output fields
     */
    public function sorted(Sort $sort, Project $project, int $fields): Project
    {
        $values = [];
        $keys = [];
        $expressions = $project->expressions;
        foreach ($sort->keys as [$position, $domain, $descending]) {
            $slot = $project->input->width() + count($values);
            $values[] = $project->expressions[$position];
            $keys[] = [$slot, $domain, $descending];
            if ($position >= $fields) {
                $expressions[$position] = new ColumnRead($domain, $slot);
            }
        }
        $input = new Sort(new Project($project->input, $values, true), $keys);

        return new Project($input, array_values($expressions));
    }
}
