<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Grouping;

use MySqlMemory\Evaluation\Aggregate\Accumulation;
use MySqlMemory\Evaluation\Aggregate\TupleInput;
use MySqlMemory\Evaluation\Compile\Constancy;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Plan\Planner;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\JsonObjectAggregate;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableAssignment;
use SqlSemantics\Statement\Operand;

/**
 * Plans constant checks and row input for COUNT DISTINCT and GROUP_CONCAT.
 *
 * Materialized window input has already executed all its fields. Direct grouping
 * evaluates complete tuples; modern implicit aggregation defers table-independent
 * fields. Legacy releases read variable assignments for every tuple.
 *
 * @visibility MySqlMemory
 */
final class Tuples
{
    /**
     * @param Planner $planner The current statement planner
     */
    public function __construct(public readonly Planner $planner)
    {
    }

    /**
     * Attaches evaluation phases to a direct tuple aggregate.
     */
    public function plan(Select $select, Aggregate|GroupConcat|JsonObjectAggregate $node, Accumulation $accumulation): Accumulation
    {
        if (!$node instanceof GroupConcat && !($node instanceof Aggregate && $node->function === AggregateFunction::Count && $node->distinct)) {
            return $accumulation;
        }
        $constants = [];
        $checks = [];
        $disabled = false;
        foreach ($node->arguments as $position => $argument) {
            if ($this->omitted($select, $node, $argument)) {
                $constants[] = $position;
            }
            if (!$this->checkable($argument, $node instanceof GroupConcat || $this->planner->settings->legacy())) {
                continue;
            }
            if ($node instanceof GroupConcat) {
                $value = $accumulation->arguments[$position]->evaluate(new Frame($this->planner->compiler->connection->context));
                $disabled = $disabled || $value === null;
            } else {
                $checks[] = $accumulation->arguments[$position];
            }
        }
        $order = $accumulation->order;
        if ($node instanceof GroupConcat && $select->groupBy === null && !$this->planner->settings->legacy()) {
            $order = array_map(fn (array $item, int $position): array => $this->constant($node->order[$position]->expression) && $this->planner->compiler->constancy($node->order[$position]->expression) !== Constancy::Resolved ? [new Constant($item[0]->domain(), null), $item[1]] : $item, $order, array_keys($order));
        }
        $input = new TupleInput($select->groupBy !== null || $this->planner->settings->legacy(), !$node instanceof GroupConcat, $constants, $checks, $disabled);

        return new Accumulation($accumulation->function, $accumulation->arguments, $accumulation->distinct, $accumulation->domain, $order, $accumulation->separator, $accumulation->limit, $accumulation->object, $input);
    }

    /**
     * Identifies fields omitted from COUNT keys or read after implicit concatenation input.
     * Legacy assignments of resolved constants freeze after their one NULL check;
     * assignments that read session or row values still run for every tuple.
     */
    public function omitted(Select $select, Aggregate|GroupConcat|JsonObjectAggregate $node, Operand $argument): bool
    {
        $legacy = $this->planner->settings->legacy();
        $plain = $this->planner->compiler->constancy($argument)->constant();
        $frozen = $legacy && $node instanceof Aggregate && $this->checkable($argument);

        return ($legacy ? ($plain || $frozen) : $this->constant($argument))
            && ($select->groupBy === null || $node instanceof GroupConcat || $plain || $frozen);
    }

    /**
     * Identifies fields that need no row storage during implicit aggregation.
     */
    public function constant(Operand $argument): bool
    {
        return Constancy::of($argument, $this->planner->compiler->facts, assignmentsVary: false, legacy: $this->planner->settings->legacy())->constant();
    }

    /**
     * Identifies preparation-time values, including assignments of resolved values.
     */
    public function checkable(Operand $argument, bool $runtime = false): bool
    {
        $constancy = $this->planner->compiler->constancy($argument instanceof VariableAssignment ? $argument->value : $argument);

        return $runtime ? $constancy->constant() : $constancy === Constancy::Resolved;
    }
}
