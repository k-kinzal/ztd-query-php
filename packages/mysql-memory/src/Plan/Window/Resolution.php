<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Window;

use LogicException;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Evaluation\Compile\Walker;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\JsonObjectAggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec;
use SqlSemantics\Platform\MySql\Statement\Call\WindowSpecification;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\WindowDefinition;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Resolves the windows of a query block and checks them, in the order the server checks them.
 *
 * The windows are the named windows of the WINDOW clause, in written order, then the windows
 * written after OVER, in the order of their calls; a call that names a window is computed over
 * it. The server refuses a cycle of windows that refine each other first (ER_WINDOW_CIRCULARITY_IN_WINDOW_GRAPH);
 * then, window by window, a PARTITION BY or ORDER BY item that is an integer, which older
 * releases read as a position (ER_WINDOW_ILLEGAL_ORDER_BY), and one holding a window function
 * (ER_WINDOW_NESTED_WINDOW_FUNC_USE_IN_WINDOW_SPEC); then, window by window, a window that
 * refines another and has PARTITION BY (ER_WINDOW_NO_CHILD_PARTITIONING), ORDER BY when the
 * other has one too (ER_WINDOW_NO_REDEFINE_ORDER_BY), or refines one with a frame
 * (ER_WINDOW_NO_INHERIT_FRAME). Every window is checked, whether a call uses it or not
 * (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/window-functions-named-windows.html.
 *
 * @visibility MySqlMemory
 */
final class Resolution
{
    /**
     * The classes of the calls that can be computed over a window.
     */
    public const CALLS = [WindowFunction::class, Aggregate::class, GroupConcat::class, JsonObjectAggregate::class];

    /**
     * @var array<string, WindowDefinition> The named windows of the block, by lower-case name
     */
    public array $named = [];

    /**
     * Resolves the windows of a block and gives each the calls computed over it.
     *
     * @param list<WindowFunction|Aggregate|GroupConcat|JsonObjectAggregate> $calls The window function calls of the block, in written order
     * @return list<Specification>
     * @throws \MySqlMemory\Error\SqlError When a window is refused
     */
    public function resolve(Select $select, array $calls): array
    {
        $specifications = $this->windows($select->windows, array_map(static fn ($call): Name|WindowSpecification => $call->over ?? throw new LogicException('A collected window call retains its OVER clause.'), $calls));
        $nodes = [];
        foreach ($specifications as $specification) {
            $nodes[spl_object_id($specification->node)] = $specification;
        }
        foreach ($calls as $call) {
            $over = $call->over;
            $node = $over instanceof Name ? $this->specification($this->definition($over)->specification) : $over;
            if ($node !== null && isset($nodes[spl_object_id($node)])) {
                $nodes[spl_object_id($node)]->calls[] = $call;
            }
        }

        return $specifications;
    }

    /**
     * Resolves retained named definitions and written OVER references without inventing a SELECT.
     *
     * @param list<WindowDefinition> $definitions Complete named windows
     * @param list<Name|WindowSpecification> $references Reached calls in written order
     * @return list<Specification>
     * @throws \MySqlMemory\Error\SqlError When a window is refused
     */
    public function windows(array $definitions, array $references): array
    {
        $this->named = [];
        $written = [];
        foreach ($definitions as $definition) {
            $this->named[strtolower($definition->name->value)] ??= $definition;
            $written[] = [$definition->name->value, $this->specification($definition->specification)];
        }
        foreach ($references as $over) {
            if ($over instanceof WindowSpecification) {
                $written[] = [Specification::UNNAMED, $this->specification($over)];
            }
        }
        foreach ($this->named as $definition) {
            $this->acyclic($definition);
        }
        foreach ($written as [$name, $node]) {
            $this->items($name, $node);
        }
        foreach ($written as [$name, $node]) {
            $this->inheritance($name, $node);
        }
        $specifications = [];
        foreach ($written as [$name, $node]) {
            $specification = new Specification($name, $node, $this->partition($node), $this->order($node));
            $specifications[] = $specification;
        }
        return $specifications;
    }

    /**
     * Answers a window as written, a parenthesized specification.
     *
     * @throws \MySqlMemory\Error\SqlError When the window is of another form
     */
    public function specification(WindowSpecification $specification): WindowSpec
    {
        return $specification instanceof WindowSpec ? $specification : throw StatementError::NotSupportedYet->error('this window specification');
    }

    /**
     * Answers the named window a name refers to.
     *
     * @throws \MySqlMemory\Error\SqlError When the block has no window of the name
     */
    public function definition(Name $name): WindowDefinition
    {
        return $this->named[strtolower($name->value)] ?? throw QueryError::WindowNotDefined->error($name->value);
    }

    /**
     * Checks that following the windows a named window refines never comes back to a window already met.
     *
     * @throws \MySqlMemory\Error\SqlError When the windows form a cycle
     */
    public function acyclic(WindowDefinition $definition): void
    {
        $seen = [];
        $node = $this->specification($definition->specification);
        while ($node->base !== null) {
            $key = strtolower($node->base->value);
            if (isset($seen[$key])) {
                throw QueryError::WindowCircularity->error();
            }
            $seen[$key] = true;
            $node = $this->specification($this->definition($node->base)->specification);
        }
    }

    /**
     * Checks the PARTITION BY and ORDER BY items of a window: none is an integer, and none holds a window function outside a subquery.
     *
     * @throws \MySqlMemory\Error\SqlError When an item is refused
     */
    public function items(string $name, WindowSpec $node): void
    {
        foreach ([...$node->partition, ...$node->order] as $item) {
            $this->item($name, $item);
        }
    }

    /**
     * Checks one partition or ordering item before resolving the following item.
     *
     * @throws \MySqlMemory\Error\SqlError When an ordinal or nested window is used
     */
    public function item(string $name, OrderItem $item): void
    {
        $expression = $item->expression;
        while ($expression instanceof Grouped) {
            $expression = $expression->operand;
        }
        if ($expression instanceof NumberLiteral && ctype_digit($expression->text)) {
            throw QueryError::WindowPositionalOrdering->error($name);
        }
        foreach (self::CALLS as $class) {
            foreach ((new Walker())->find($item->expression, $class, false) as $call) {
                if ($call->over !== null) {
                    throw QueryError::WindowFunctionInSpecification->error($name);
                }
            }
        }
    }

    /**
     * Checks what a window inherits from the window it refines.
     *
     * @throws \MySqlMemory\Error\SqlError When the window cannot refine that window
     */
    public function inheritance(string $name, WindowSpec $node): void
    {
        if ($node->base === null) {
            return;
        }
        $definition = $this->definition($node->base);
        $base = $this->specification($definition->specification);
        if ($node->partition !== []) {
            throw QueryError::WindowDependentPartitioning->error();
        }
        if ($node->order !== [] && $this->order($base) !== []) {
            throw QueryError::WindowInheritedOrdering->error($name, $definition->name->value);
        }
        if ($base->frame !== null) {
            throw QueryError::WindowInheritedFrame->error($definition->name->value);
        }
    }

    /**
     * Answers the PARTITION BY items of a window: those of the window it refines, or its own.
     *
     * @return list<OrderItem>
     * @throws \MySqlMemory\Error\SqlError When a window it refines is not defined
     */
    public function partition(WindowSpec $node): array
    {
        return $node->base === null ? $node->partition : $this->partition($this->specification($this->definition($node->base)->specification));
    }

    /**
     * Answers the ORDER BY items of a window: its own, or else those of the window it refines.
     *
     * @return list<OrderItem>
     * @throws \MySqlMemory\Error\SqlError When a window it refines is not defined
     */
    public function order(WindowSpec $node): array
    {
        if ($node->order !== [] || $node->base === null) {
            return $node->order;
        }

        return $this->order($this->specification($this->definition($node->base)->specification));
    }

    /**
     * Answers the name the server gives a window function in its messages.
     */
    public static function named(\SqlSemantics\Statement\Operand $call): string
    {
        return match (true) {
            $call instanceof WindowFunction => strtolower($call->kind->value),
            $call instanceof Aggregate => strtolower($call->function->value),
            $call instanceof GroupConcat => 'group_concat',
            $call instanceof JsonObjectAggregate => 'json_objectagg',
            default => '',
        };
    }
}
