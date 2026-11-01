<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Window;

use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Constancy;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Evaluation\Window\OutputRead;
use MySqlMemory\Evaluation\Window\WindowFrame;
use MySqlMemory\Plan\Grouping;
use MySqlMemory\Plan\Path\AccessPath;
use MySqlMemory\Plan\Path\Transform\Window;
use MySqlMemory\Plan\Planner;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\JsonObjectAggregate;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\NestedRelation;
use SqlSemantics\Platform\MySql\Statement\Relation\TableList;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Statement\Operand;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Reference\Column\AliasTarget;

/**
 * Plans the windows of a query block: after its grouping and HAVING, before its DISTINCT, ORDER BY and LIMIT.
 *
 * Window functions are written in the select list and ORDER BY; anywhere else, in an argument of
 * another window function or of an aggregate included, they are refused
 * (ER_WINDOW_INVALID_WINDOW_FUNC_USE). The windows that have calls are computed one after the other,
 * the named windows in the order of the WINDOW clause and then the others in written order, each
 * sorting the rows the previous one answered, so the rows leave in the order of the last one that
 * sorts. When no window sorts, a block that reads one table without grouping or DISTINCT sorts its
 * rows for its ORDER BY before the windows, unless the ORDER BY reads a window function or a
 * function such as RAND() whose value differs for each call
 * (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/window-functions-usage.html,
 * https://dev.mysql.com/doc/refman/8.4/en/window-function-optimization.html.
 *
 * @visibility MySqlMemory
 */
final class Windowing
{
    /**
     * @param Planner $planner The planner of the statement
     */
    public function __construct(public readonly Planner $planner)
    {
    }

    /**
     * Plans the windows of a block over its rows, binds each call to its value in the scope, and answers the path and whether it already sorts the rows for the ORDER BY of the block.
     *
     * @return array{AccessPath, bool, bool} The path, whether it is presorted, and whether window outputs are materialized
     * @throws SqlError When a window function or a window is refused
     */
    public function plan(Select $select, AccessPath $input, Scope $scope): array
    {
        $calls = $this->calls($select);
        if ($calls === [] && $select->windows === []) {
            return [$input, false, false];
        }
        if (array_filter($select->windows, static fn ($window): bool => $window->specification instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredWindowSpec) !== []) {
            (new Collected($this->planner, $input->width()))->dependencies(array_map(static fn ($window): array => [$window->name->value, $window->specification], $select->windows), $scope);
        }
        $windows = (new Resolution())->resolve($select, $calls);
        $functions = new Functions($this->planner);
        foreach ($calls as $call) {
            if ($call instanceof WindowFunction) {
                $functions->options($call);
            }
        }
        $frames = new Frames($this->planner);
        foreach ($windows as $window) {
            $frames->check($window, $scope, $window->calls !== []);
        }
        $analytics = [];
        $direct = $this->direct($select, $scope);
        $input = (new SiblingInputs($this->planner))->grouped($select, $input, $scope, $direct);
        foreach ($calls as $call) {
            $analytics[spl_object_id($call)] = $functions->compile($call, $scope, $direct);
        }
        if ((new Direct($this->planner))->constant($select)) {
            (new Direct($this->planner))->bind($select, $scope, $windows, $analytics);

            return [$input, false, false];
        }
        return $this->buffered($select, $input, $scope, $windows, $analytics, $direct);
    }

    /**
     * Builds buffered partitions and binds their outputs after every window is compiled.
     *
     * @param list<Specification> $windows The resolved windows
     * @param array<int, \MySqlMemory\Evaluation\Window\Analytic> $analytics The compiled calls
     * @return array{AccessPath, bool, bool}
     */
    public function buffered(Select $select, AccessPath $input, Scope $scope, array $windows, array $analytics, bool $direct): array
    {
        $input = (new ParentInputs($this->planner))->plan($select, $input, $scope, $direct);
        $frames = new Frames($this->planner);
        $functions = new Functions($this->planner);
        $used = array_values(array_filter($windows, static fn (Specification $window): bool => $window->calls !== []));
        $pushed = $used !== [] && $this->pushes($select, $used);
        if ($pushed) {
            $input = new Window($input, [], $this->ordering($select, $scope), WindowFrame::default(false), []);
        }
        $compiler = $this->planner->compiler;
        $bindings = [];
        foreach ($used as $windowIndex => $window) {
            $partition = array_map(static fn (OrderItem $item): Evaluable => $compiler->compile($item->expression, $scope), $window->partition);
            $order = array_map(static fn (OrderItem $item): array => [$compiler->compile($item->expression, $scope), $item->direction?->value === 'DESC'], $window->order);
            $width = $input->width();
            $computed = [];
            $arguments = [];
            foreach ($window->calls as $call) {
                [$computed[], $read] = $functions->cached($analytics[spl_object_id($call)], $width + count($arguments), $call);
                $arguments = [...$arguments, ...$read];
            }
            $path = new Window($input, $partition, $order, $frames->compile($window, $order, $scope), $computed, $arguments);
            $copies = $windowIndex === 0 ? (new SiblingInputs($this->planner))->streamed($select, $path, $scope, $direct, $windowIndex === array_key_last($used)) : [];
            $input = new Window($input, $partition, $order, $path->frame, $computed, $arguments, $copies);
            foreach ($window->calls as $index => $call) {
                $read = new OutputRead($analytics[spl_object_id($call)], $width + $index, $direct, $select->from !== null && !$select->from instanceof \SqlSemantics\Platform\MySql\Statement\Relation\Dual);
                $logical = $compiler->domain($call);
                $last = $windowIndex === array_key_last($used);
                $bindings[] = [$call, !$direct && $logical->kind->temporal() && (new \SqlSemantics\Platform\MySql\Rules\Query\Result\TemporalFields())->retainsWindow($select, $call, $last) ? new \MySqlMemory\Evaluation\Leaf\Retyped($read, $logical) : $read];
            }
        }
        foreach ($bindings as [$call, $read]) {
            $scope->bind($call, $read);
        }

        return [(new ParentInputs($this->planner))->plan($select, $input, $scope, $direct, true), $pushed, !$direct];
    }

    /**
     * Chooses result-column storage after parent expressions have resolved their logical types.
     * Unrelated expressions keep their own output rules; a bare window already has its chosen type.
     */
    public function output(Select $select, \SqlSemantics\Statement\Shape\Field $field, Evaluable $expression, bool $materialized): \MySqlMemory\Typing\Domain
    {
        $domain = $expression->domain();
        if ($expression instanceof OutputRead) {
            return $domain;
        }
        $stored = (new \SqlSemantics\Platform\MySql\Rules\Query\Result\OutputFields())->plainStored($select, $field->expression, $domain->resolved(), $this->planner->settings->release());
        if (($domain->kind === \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::String || $domain->kind === \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Json || $domain->kind->temporal()) && $field->expression !== null && ($stored || (new \SqlSemantics\Platform\MySql\Rules\Query\Aggregation())->aggregates([$field->expression])) && $field->type instanceof \SqlSemantics\Statement\Type\Known && $field->type->descriptor instanceof \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain) {
            return \MySqlMemory\Typing\Domain::of($field->type->descriptor, $domain->nullable);
        }
        if ($field->expression !== null && $this->reads($field->expression)) {
            return $materialized ? \MySqlMemory\Typing\Domain::of((new \SqlSemantics\Platform\MySql\Rules\Typing\Materialization())->windowParent($domain->resolved(), $this->planner->settings->release(), $field->expression), $domain->nullable) : $domain;
        }

        return $this->planner->blocks->tabled($field, $domain);
    }

    /**
     * Answers whether an empty or constant query reports window types without temporary-column conversion.
     */
    public function direct(Select $select, Scope $scope): bool
    {
        if ($select->from === null || $select->from instanceof \SqlSemantics\Platform\MySql\Statement\Relation\Dual || $this->planner->blocks->empty($select, $scope->outer) || (new \MySqlMemory\Plan\ConstantTables($this->planner->compiler->facts))->joined($select) === 0) {
            return true;
        }
        if ($select->groupBy === null) {
            return (new Grouping($this->planner))->collect($select) !== [];
        }
        if ($select->groupBy->modifier !== null) {
            return false;
        }
        foreach ($select->groupBy->items as $item) {
            if (!$this->planner->compiler->constancy($item->expression)->constant() || (new Walker())->find($item->expression, Query::class) !== []) {
                return false;
            }
        }

        return true;
    }

    /**
     * Finds the window function calls of a block in its select list and ORDER BY, outside its subqueries, in written order.
     *
     * A call written in the window of another is left to the check of that window.
     *
     * @return list<WindowFunction|Aggregate|GroupConcat|JsonObjectAggregate>
     * @throws SqlError When a call is written in an argument of another
     */
    public function calls(Select $select): array
    {
        $walker = new Walker();
        $found = [];
        foreach ($this->roots($select) as $root) {
            foreach ($walker->find($root, Operand::class, false) as $node) {
                if (($node instanceof WindowFunction || $node instanceof Aggregate || $node instanceof GroupConcat || $node instanceof JsonObjectAggregate) && self::windowed($node)) {
                    $found[] = $node;
                }
            }
        }
        $inner = [];
        foreach ($found as $call) {
            $over = $call->over instanceof WindowSpec ? $walker->find($call->over, Operand::class, false) : [];
            foreach ($over as $node) {
                $inner[spl_object_id($node)] = true;
            }
            foreach ($walker->find($call, Operand::class, false) as $node) {
                if ($node !== $call && self::windowed($node) && !isset($inner[spl_object_id($node)])) {
                    throw QueryError::WindowFunctionMisplaced->error(Resolution::named($node));
                }
            }
        }

        return array_values(array_filter($found, static fn ($call): bool => !isset($inner[spl_object_id($call)])));
    }

    /**
     * Answers the expressions of the select list and ORDER BY of a block.
     *
     * @return list<Operand>
     */
    public function roots(Select $select): array
    {
        $roots = [];
        foreach ($select->items as $item) {
            if ($item instanceof SelectExpression) {
                $roots[] = $item->expression;
            }
        }
        foreach ([...$select->orderBy, ...($select->late === null ? [] : $select->late->orderBy)] as $item) {
            $roots[] = $item->expression;
        }

        return $roots;
    }

    /**
     * Tells whether a node is a call computed over a window.
     */
    public static function windowed(object $node): bool
    {
        return $node instanceof WindowFunction || (($node instanceof Aggregate || $node instanceof GroupConcat || $node instanceof JsonObjectAggregate) && $node->over !== null);
    }

    /**
     * Tells whether the block sorts its rows for its ORDER BY before its windows: it reads one table without grouping or DISTINCT, no window sorts, and the ORDER BY reads no window function.
     *
     * @param list<Specification> $windows The windows that have calls
     */
    public function pushes(Select $select, array $windows): bool
    {
        $order = [...$select->orderBy, ...($select->late === null ? [] : $select->late->orderBy)];
        if ($order === [] || $select->groupBy !== null || in_array(SelectOption::Distinct, $select->options, true) || (new Grouping($this->planner))->collect($select) !== []) {
            return false;
        }
        $from = $select->from;
        while ($from instanceof NestedRelation || ($from instanceof TableList && count($from->members) === 1)) {
            $from = $from instanceof NestedRelation ? $from->relation : $from->members[0];
        }
        if (!$from instanceof TableReference && !$from instanceof DerivedTable) {
            return false;
        }
        foreach ($windows as $window) {
            if ($window->partition !== [] || $window->order !== []) {
                return false;
            }
        }
        foreach ($order as $item) {
            if ($this->reads($item->expression) || (new Walker())->find((new Grouping($this->planner))->target($item->expression), \SqlSemantics\Platform\MySql\Statement\Variable\VariableAssignment::class, false) !== []) {
                return false;
            }
            foreach ((new Walker())->find($item->expression, FunctionCall::class, false) as $call) {
                if (in_array(strtoupper($call->name->value), Constancy::ROW_FUNCTIONS, true)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Tells whether an expression reads a window function, itself or through the alias or position of a select item.
     */
    public function reads(Operand $expression): bool
    {
        $facts = $this->planner->compiler->facts;
        foreach ((new Walker())->find($expression, Operand::class, false) as $node) {
            if (self::windowed($node)) {
                return true;
            }
            $resolution = ($node instanceof ColumnUse || $node instanceof OutputOrdinal) && $node->hasBinding() ? $node->scalarInformation()->resolution : null;
            if ($resolution instanceof AliasTarget && $resolution->field->expression !== null && $this->reads($resolution->field->expression)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Compiles the ORDER BY of a block over its rows before the windows; a key constant for the statement does not sort.
     *
     * @return list<array{Evaluable, bool}>
     * @throws SqlError When a key cannot be compiled
     */
    public function ordering(Select $select, Scope $scope): array
    {
        $compiler = $this->planner->compiler;
        $keys = [];
        foreach ([...$select->orderBy, ...($select->late === null ? [] : $select->late->orderBy)] as $item) {
            $key = $compiler->compile($item->expression, $scope);
            if ($compiler->constancy($item->expression)->constant() && (new Walker())->find($item->expression, Query::class) === []) {
                continue;
            }
            $keys[] = [$key, $item->direction?->value === 'DESC'];
        }

        return $keys;
    }
}
