<?php

declare(strict_types=1);

namespace MySqlMemory\Command\View;

use MySqlMemory\Dictionary\Routine;
use MySqlMemory\Evaluation\Compile\Walker;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Window\Frame;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBound;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec;
use SqlSemantics\Platform\MySql\Statement\Call\WindowSpecification;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;

/**
 * Writes the windows of the query of a view as the server stores them.
 *
 * A window function is written in lower case without RESPECT NULLS or FROM FIRST, followed by
 * OVER and the quoted name of its window or its specification. A specification is written in
 * parentheses with the window it refines, PARTITION BY and ORDER BY each followed by a space, and
 * the frame in the BETWEEN form, an INTERVAL offset with its unit in lower case; a space follows
 * the parentheses. The WINDOW clause, written after HAVING, keeps only the named windows a call
 * uses, itself or through the windows that refine them, separated by ` , ` (verified on a live
 * 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-create-view.html.
 *
 * @visibility MySqlMemory
 */
final class WindowText
{
    /**
     * @param ExpressionText $expressions The writer of the expressions of the query
     */
    public function __construct(public readonly ExpressionText $expressions)
    {
    }

    /**
     * Writes a call of a function that is only a window function.
     */
    public function call(WindowFunction $call): ?string
    {
        $arguments = $this->expressions->scalars($call->arguments);
        $over = $this->over($call->over);

        return $arguments === null || $over === null ? null : strtolower($call->kind->value) . '(' . implode(',', $arguments) . ')' . $over;
    }

    /**
     * Writes an aggregate computed over a window.
     */
    public function aggregate(Aggregate $aggregate): ?string
    {
        if ($aggregate->function === AggregateFunction::JsonArray || $aggregate->function === AggregateFunction::Collect) {
            return null;
        }
        $written = $aggregate->arguments === [] ? ['0'] : $this->expressions->scalars($aggregate->arguments);
        $over = $aggregate->over === null ? null : $this->over($aggregate->over);

        return $written === null || $over === null ? null : strtolower($aggregate->function->value) . '(' . implode(',', $written) . ')' . $over;
    }

    /**
     * Writes OVER and a window: its quoted name, or its specification.
     */
    public function over(Name|WindowSpecification $over): ?string
    {
        if ($over instanceof Name) {
            return ' OVER ' . Routine::quoted($over->value);
        }
        $specification = $over instanceof WindowSpec ? $this->specification($over) : null;

        return $specification === null ? null : ' OVER ' . $specification;
    }

    /**
     * Writes a window specification in parentheses, followed by a space.
     */
    public function specification(WindowSpec $window): ?string
    {
        $text = '(' . ($window->base === null ? '' : Routine::quoted($window->base->value) . ' ');
        foreach (['PARTITION BY' => $window->partition, 'ORDER BY' => $window->order] as $keywords => $items) {
            if ($items === []) {
                continue;
            }
            $keys = $this->expressions->scalars(array_map(static fn (OrderItem $item) => $item->expression, $items));
            if ($keys === null) {
                return null;
            }
            $text .= $keywords . ' ' . implode(',', array_map(static fn (string $key, OrderItem $item): string => $key . ($item->direction?->value === 'DESC' ? ' desc' : ''), $keys, $items)) . ' ';
        }
        $frame = $window->frame === null ? '' : $this->frame($window->frame);

        return $frame === null ? null : $text . $frame . ') ';
    }

    /**
     * Writes a frame in the BETWEEN form.
     */
    public function frame(Frame $frame): ?string
    {
        if ($frame->exclusion !== null) {
            return null;
        }
        $start = $this->bound($frame->start);
        $end = $frame->end === null ? 'CURRENT ROW' : $this->bound($frame->end);

        return $start === null || $end === null ? null : $frame->unit->value . ' BETWEEN ' . $start . ' AND ' . $end;
    }

    /**
     * Writes one boundary of a frame.
     */
    public function bound(FrameBound $bound): ?string
    {
        if ($bound->offset === null) {
            return $bound->kind->value;
        }
        $offset = $this->expressions->scalar($bound->offset);
        if ($offset === null) {
            return null;
        }

        return $bound->unit === null ? $offset . ' ' . $bound->kind->value : 'INTERVAL ' . $offset . ' ' . strtolower($bound->unit->value) . '  ' . $bound->kind->value;
    }

    /**
     * Writes the WINDOW clause of a query block: the named windows its calls use, or nothing when they use none.
     */
    public function clause(Select $select): ?string
    {
        $named = [];
        foreach ($select->windows as $definition) {
            $named[strtolower($definition->name->value)] = $definition;
        }
        $used = [];
        $pending = $this->used($select);
        while (($name = array_pop($pending)) !== null) {
            $key = strtolower($name);
            if (isset($used[$key]) || !isset($named[$key])) {
                continue;
            }
            $used[$key] = true;
            $specification = $named[$key]->specification;
            if ($specification instanceof WindowSpec && $specification->base !== null) {
                $pending[] = $specification->base->value;
            }
        }
        $windows = [];
        foreach ($select->windows as $definition) {
            if (!isset($used[strtolower($definition->name->value)])) {
                continue;
            }
            $specification = $definition->specification instanceof WindowSpec ? $this->specification($definition->specification) : null;
            if ($specification === null) {
                return null;
            }
            $windows[] = Routine::quoted($definition->name->value) . ' AS ' . $specification;
        }

        return $windows === [] ? '' : ' window ' . implode(', ', $windows);
    }

    /**
     * Answers the names of the windows the calls of a query block use: those written after OVER, and those the windows written after OVER refine.
     *
     * @return list<string>
     */
    public function used(Select $select): array
    {
        $names = [];
        $walker = new Walker();
        $roots = [...array_map(static fn (object $item): object => $item instanceof SelectExpression ? $item->expression : $item, $select->items), ...array_map(static fn (OrderItem $item): Scalar => $item->expression, $select->orderBy)];
        foreach ($roots as $root) {
            foreach ($walker->find($root, Scalar::class, false) as $node) {
                $over = $node instanceof WindowFunction || $node instanceof Aggregate ? $node->over : null;
                if ($over instanceof Name) {
                    $names[] = $over->value;
                }
                if ($over instanceof WindowSpec && $over->base !== null) {
                    $names[] = $over->base->value;
                }
            }
        }

        return $names;
    }
}
