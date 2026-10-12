<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Window;

use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Leaf\Assignment;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Evaluation\Leaf\Retyped;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Evaluation\Window\EmissionAssignment;
use MySqlMemory\Evaluation\Window\InputRead;
use MySqlMemory\Plan\Path\AccessPath;
use MySqlMemory\Plan\Path\Transform\Project;
use MySqlMemory\Plan\Path\Transform\Window;
use MySqlMemory\Plan\Planner;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Grouping\Matching;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\JsonObjectAggregate;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableAssignment;
use SqlSemantics\Statement\Scalar;

/**
 * Stores ordinary select expressions alongside the input of a buffered window.
 *
 * Window arguments and parent reads are captured before ordinary assignments.
 * Grouped rows retain those inputs before window output begins; ROLLUP keeps
 * the previous group's copies. Ordinary bare assignments restore their stored
 * input value as the window consumes each grouped row. MySQL 8.0 also publishes
 * their copied value when a result row is emitted. Later releases read the
 * stored output without that additional assignment. Verified through PDO values,
 * result metadata and subsequent variable reads on MySQL 8.0, 8.4 and 9.1.
 *
 * @visibility MySqlMemory
 */
final class SiblingInputs
{
    /**
     * @param Planner $planner The statement planner
     */
    public function __construct(public readonly Planner $planner)
    {
    }

    /**
     * Collects ordinary variable-dependent outputs when at least one assigns a variable.
     *
     * @return list<Scalar>
     */
    public function roots(Select $select): array
    {
        $windowing = new Windowing($this->planner);
        $roots = array_values(array_filter($windowing->roots($select), static fn (Scalar $root): bool => !$windowing->reads($root) && (new Walker())->find($root, UserVariable::class, false) !== []));
        $writes = array_filter($roots, static fn (Scalar $root): bool => (new Walker())->find($root, VariableAssignment::class, false) !== []);

        return $writes === [] ? [] : $roots;
    }

    /**
     * Collects changing window arguments and parent reads before ordinary assignments.
     *
     * @return array<int, Scalar> The expressions keyed by node identity
     */
    public function nodes(Select $select): array
    {
        $windowing = new Windowing($this->planner);
        $nodes = [];
        foreach ($windowing->calls($select) as $call) {
            $arguments = $call instanceof JsonObjectAggregate ? [$call->key, $call->value] : $call->arguments;
            foreach ($arguments as $argument) {
                if ((new Arguments($this->planner))->stored($argument)) {
                    $nodes[spl_object_id($argument)] = $argument;
                }
            }
        }
        $variables = [];
        foreach ($windowing->roots($select) as $root) {
            if ($windowing->reads($root)) {
                (new ParentInputs($this->planner))->visit($root, $variables);
            }
        }

        return $nodes + $variables;
    }

    /**
     * Captures grouped inputs and ordinary expressions before compiling window operands.
     */
    public function grouped(Select $select, AccessPath $input, Scope $scope, bool $direct): AccessPath
    {
        if ($direct || $select->groupBy === null || $this->roots($select) === [] || (new Windowing($this->planner))->calls($select) === []) {
            return $input;
        }
        $values = [];
        foreach ($this->nodes($select) as $node) {
            $value = $this->planner->compiler->compile($node, $scope);
            $scope->bind($node, $this->stored($value, $input->width() + count($values), $select->groupBy->modifier === null));
            $values[] = $value;
        }
        foreach ($this->roots($select) as $node) {
            $value = $this->planner->compiler->compile($node, $scope);
            $scope->bind($node, new ColumnRead($value->domain(), $input->width() + count($values)));
            $values[] = $value;
        }

        return new Project($input, $values, true, $select->groupBy->modifier === null ? null : $input->width() - 1);
    }

    /**
     * Reads a grouped field, preserving a bare assignment's input effect when requested.
     */
    public function stored(Evaluable $value, int $position, bool $restore): Evaluable
    {
        $read = new ColumnRead($value->domain(), $position);
        $unwrapped = $value;
        while ($unwrapped instanceof Retyped) {
            $unwrapped = $unwrapped->evaluable;
        }
        if ($restore && $unwrapped instanceof Assignment) {
            return new Assignment($unwrapped->name, $read, $unwrapped->stored, $unwrapped->domain());
        }

        return $read;
    }

    /**
     * Appends copies as each input row is demanded, after window arguments are captured.
     * The last window can expose its current input field; another window stores it per row.
     *
     * @return list<Evaluable>
     */
    public function streamed(Select $select, Window $window, Scope $scope, bool $direct, bool $last = true): array
    {
        if ($direct || $this->roots($select) === [] || $select->groupBy?->modifier !== null) {
            return [];
        }
        $values = [];
        $variables = [];
        $windowing = new Windowing($this->planner);
        foreach ($windowing->roots($select) as $root) {
            if ($select->groupBy === null && $windowing->reads($root)) {
                (new ParentInputs($this->planner))->visit($root, $variables);
            }
        }
        foreach ([...array_values($variables), ...$this->roots($select)] as $node) {
            $value = $this->planner->compiler->compile($node, $scope);
            $position = $window->width() + count($values);
            $read = new ColumnRead($value->domain(), $position);
            $target = (new Matching())->unwrap($node);
            if ($target instanceof VariableAssignment && $this->planner->settings->release() === GrammarRelease::MySql8044) {
                $read = new EmissionAssignment(new Assignment($target->target->name->value, $last ? new InputRead($value->domain(), $position) : $read, $this->planner->compiler->names->stored($value->domain()), $value->domain()));
            }
            $scope->bind($node, $read);
            if ($select->groupBy !== null && $target instanceof VariableAssignment) {
                $value = new Assignment($target->target->name->value, $value, $this->planner->compiler->names->stored($value->domain()), $value->domain());
            }
            $values[] = $value;
        }

        return $values;
    }
}
