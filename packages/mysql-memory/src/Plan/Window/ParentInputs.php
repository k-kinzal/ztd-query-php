<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Window;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Leaf\Assignment;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Evaluation\Leaf\Retyped;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Path\AccessPath;
use MySqlMemory\Plan\Path\Transform\Project;
use MySqlMemory\Plan\Planner;
use SqlSemantics\Platform\MySql\Statement\Call\SetFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableAssignment;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;

/**
 * Preserves user-variable inputs around buffered windows and sorted output assignments.
 *
 * A variable read outside aggregate or window arguments is captured before window
 * parents assign their results. Ungrouped outputs independent of windows capture their
 * reads as each row arrives, before its window parents execute. Sorted assignments publish stored values
 * as result rows are emitted, including after LIMIT. Verified through SQL and PDO
 * observations on MySQL 8.0 and 9.1.
 *
 * @visibility MySqlMemory
 */
final class ParentInputs
{
    /**
     * @param Planner $planner The current statement planner
     */
    public function __construct(public readonly Planner $planner)
    {
    }

    /**
     * Appends captured inputs and binds each read to its stored column.
     *
     * @param bool $after Whether the window stages have already been planned
     */
    public function plan(Select $select, AccessPath $input, Scope $scope, bool $direct, bool $after = false): AccessPath
    {
        if ($direct || ($after ? $select->groupBy !== null : $select->groupBy === null) || (new Windowing($this->planner))->calls($select) === []) {
            return $input;
        }
        $variables = [];
        foreach ((new Windowing($this->planner))->roots($select) as $root) {
            if ($scope->bound($root) === null && (!$after || !(new Windowing($this->planner))->reads($root))) {
                $this->visit($root, $variables);
            }
        }
        $values = [];
        foreach ($variables as $node) {
            $value = $this->planner->compiler->compile($node, $scope);
            $scope->bind($node, new ColumnRead($value->domain(), $input->width() + count($values)));
            $values[] = $value;
        }

        return $values === [] ? $input : new Project($input, $values, true);
    }

    /**
     * Collects reads without descending into subqueries, aggregate arguments or assignment targets.
     *
     * @param array<int, UserVariable> $variables The distinct read nodes keyed by identity
     * @param-out array<int, UserVariable> $variables
     */
    public function visit(Node $node, array &$variables): void
    {
        if ($node instanceof Query || $node instanceof SetFunction || $node instanceof WindowFunction) {
            return;
        }
        if ($node instanceof VariableAssignment) {
            $this->visit($node->value, $variables);
            return;
        }
        if ($node instanceof UserVariable) {
            $variables[spl_object_id($node)] = $node;
            return;
        }
        $properties = get_object_vars($node);
        $children = [];
        array_walk_recursive($properties, static function ($value) use (&$children): void {
            if ($value instanceof Node) {
                $children[] = $value;
            }
        });
        foreach ($children as $child) {
            $this->visit($child, $variables);
        }
    }

    /**
     * Publishes sorted output assignments and delayed input assignments after row bounds.
     *
     * @param list<Evaluable> $expressions The projection, including ordering values
     * @param int $fields The number of visible output values
     * @param bool $sorted Whether a sort reordered the projected rows
     * @param array<int, true> $copies The assignment outputs read from stored fields
     */
    public function emitted(Select $select, AccessPath $input, array $expressions, int $fields, bool $sorted, array $copies = []): AccessPath
    {
        $emitting = array_filter($expressions, static function (Evaluable $expression): bool {
            while ($expression instanceof Retyped) {
                $expression = $expression->evaluable;
            }
            return $expression instanceof \MySqlMemory\Evaluation\Window\EmissionAssignment;
        }) !== [];
        if ((!$sorted && !$emitting && $copies === []) || (new Windowing($this->planner))->calls($select) === []) {
            return $input;
        }
        $projected = [];
        $changed = false;
        foreach ($expressions as $position => $expression) {
            $read = new ColumnRead($expression->domain(), $position);
            $value = $expression;
            while ($value instanceof Retyped) {
                $value = $value->evaluable;
            }
            if ($value instanceof \MySqlMemory\Evaluation\Window\EmissionAssignment) {
                $value = $value->assignment;
            }
            if ($position < $fields && $value instanceof Assignment) {
                $read = new Assignment($value->name, $read, isset($copies[$position]) ? $this->planner->compiler->names->stored($expression->domain()) : $value->stored, $expression->domain());
                $changed = true;
            }
            $projected[] = $read;
        }

        return $changed ? new Project($input, $projected) : $input;
    }
}
