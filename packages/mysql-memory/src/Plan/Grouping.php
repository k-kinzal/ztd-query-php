<?php

declare(strict_types=1);

namespace MySqlMemory\Plan;

use MySqlMemory\Evaluation\Aggregate\Accumulation;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Path\AccessPath;
use MySqlMemory\Plan\Path\Transform\Aggregate as AggregatePath;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Statement\Scalar;

/**
 * Plans the grouping of a query block: its GROUP BY expressions and the aggregates of its select list, HAVING and ORDER BY.
 *
 * A block groups when it has GROUP BY or an aggregate; after grouping, each aggregate is read
 * from the grouped row and every other column from the first row of the group.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/aggregate-functions.html.
 *
 * @visibility MySqlMemory
 */
final class Grouping
{
    /**
     * @param Planner $planner The planner of the statement
     */
    public function __construct(public readonly Planner $planner)
    {
    }

    /**
     * Plans the grouping of a block over its input; answers the grouped path and the scope its later clauses compile in.
     *
     * @return array{AccessPath, Scope}
     */
    public function plan(Select $select, AccessPath $input, Scope $scope): array
    {
        $aggregates = $this->collect($select);
        if ($select->groupBy === null && $aggregates === []) {
            return [$input, $scope];
        }
        $compiler = $this->planner->compiler;
        $groups = [];
        foreach ($select->groupBy === null ? [] : $select->groupBy->items as $item) {
            $groups[] = $compiler->compile($item->expression, $scope);
        }
        $accumulations = array_map(fn (Scalar $node): Accumulation => $this->accumulation($node, $scope), $aggregates);
        $grouped = $scope->grouped();
        $width = $input->width();
        foreach ($aggregates as $index => $node) {
            $grouped->bind($node, new ColumnRead($accumulations[$index]->domain, $width + $index));
        }

        return [new AggregatePath($input, $groups, $accumulations, $select->groupBy?->modifier !== null), $grouped];
    }

    /**
     * Finds the aggregates of a block in its select list, HAVING and ORDER BY, outside its subqueries.
     *
     * @return list<Aggregate|GroupConcat>
     */
    public function collect(Select $select): array
    {
        $roots = [];
        foreach ($select->items as $item) {
            if ($item instanceof SelectExpression) {
                $roots[] = $item->expression;
            }
        }
        if ($select->having !== null) {
            $roots[] = $select->having;
        }
        foreach ($select->orderBy as $item) {
            $roots[] = $item->expression;
        }
        $walker = new Walker();
        $found = [];
        foreach ($roots as $root) {
            foreach ($walker->find($root, Aggregate::class, false) as $node) {
                if ($node->over === null) {
                    $found[] = $node;
                }
            }
            foreach ($walker->find($root, GroupConcat::class, false) as $node) {
                if ($node->over === null) {
                    $found[] = $node;
                }
            }
        }

        return $found;
    }

    /**
     * Compiles an aggregate into the fold of its arguments.
     */
    public function accumulation(Aggregate|GroupConcat $node, Scope $scope): Accumulation
    {
        $compiler = $this->planner->compiler;
        $arguments = array_map(static fn (Scalar $argument): Evaluable => $compiler->compile($argument, $scope), $node->arguments);
        if ($node instanceof GroupConcat) {
            $order = array_map(static fn ($item): array => [$compiler->compile($item->expression, $scope), $item->direction?->value === 'DESC'], $node->order);
            $limit = (int) ($compiler->connection->variables->read('group_concat_max_len') ?? 1024);

            return new Accumulation(null, $arguments, $node->distinct, $compiler->domain($node), $order, $node->separator === null ? ',' : $node->separator->value, $limit);
        }

        return new Accumulation($node->function, $arguments, $node->distinct, $compiler->domain($node), [], ',', 0);
    }

}
