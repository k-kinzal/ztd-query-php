<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Grouping;

use MySqlMemory\Evaluation\Aggregate\Accumulation;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Plan\Path\AccessPath;
use MySqlMemory\Plan\Path\Transform\Project;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Plan\Window\Windowing;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Statement\Scalar;

/**
 * Captures aggregate arguments before windowed ROLLUP groups its input.
 *
 * With varying grouping keys, windows require stored aggregate inputs. Each
 * argument executes in input order once and every grouping level reads its copy.
 * Constant grouping keys retain the direct per-level argument evaluation.
 * Verified through SQL values and variable effects on MySQL 8.4.
 *
 * @visibility MySqlMemory
 */
final class Inputs
{
    /**
     * @param Planner $planner The current statement planner
     */
    public function __construct(public readonly Planner $planner)
    {
    }

    /**
     * Appends stored arguments and sorting keys, preserving each fold's domains.
     *
     * @param list<Accumulation> $accumulations The compiled aggregates
     * @param list<Scalar> $groups The resolved grouping expressions
     * @return array{AccessPath, list<Accumulation>}
     */
    public function plan(Select $select, AccessPath $input, array $accumulations, array $groups): array
    {
        if (!$this->stored($select, $groups)) {
            return [$input, $accumulations];
        }
        $values = [];
        $stored = [];
        foreach ($accumulations as $accumulation) {
            $arguments = [];
            foreach ($accumulation->arguments as $argument) {
                $arguments[] = new ColumnRead($argument->domain(), $input->width() + count($values));
                $values[] = $argument;
            }
            $order = [];
            foreach ($accumulation->order as [$key, $descending]) {
                $order[] = [new ColumnRead($key->domain(), $input->width() + count($values)), $descending];
                $values[] = $key;
            }
            $stored[] = new Accumulation($accumulation->function, $arguments, $accumulation->distinct, $accumulation->domain, $order, $accumulation->separator, $accumulation->limit, $accumulation->object);
        }

        return [$values === [] ? $input : new Project($input, $values, true), $stored];
    }

    /**
     * Identifies windows whose ROLLUP grouping keys require stored aggregate input.
     *
     * @param list<Scalar> $groups The resolved grouping expressions
     */
    public function stored(Select $select, array $groups): bool
    {
        if ($this->planner->settings->legacy() || $select->groupBy?->modifier === null || (new Windowing($this->planner))->calls($select) === []) {
            return false;
        }
        foreach ($groups as $group) {
            if (!$this->planner->compiler->constancy($group)->constant()) {
                return true;
            }
        }

        return false;
    }

}
