<?php

declare(strict_types=1);

namespace MySqlMemory\Plan;

use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Path\RecursiveUnion;
use MySqlMemory\Plan\Path\WorkingTable;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetQuantifier;
use SqlSemantics\Platform\MySql\Statement\Query\With\CommonTableExpression;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Reference\Table\CommonTable;

/**
 * Plans a recursive common table expression: the union of a nonrecursive part and a part that reads the rows of the last iteration.
 *
 * The columns take the types of the nonrecursive part and may be NULL.
 *
 * @visibility MySqlMemory
 */
final class Recursion
{
    /**
     * @param Planner $planner The planner of the statement
     */
    public function __construct(public readonly Planner $planner)
    {
    }

    /**
     * Plans an expression that refers to itself, or answers null for one that does not.
     */
    public function plan(CommonTableExpression $definition, ?Scope $outer): ?QueryPlan
    {
        $query = $definition->query;
        while ($query instanceof ParenthesizedQuery || ($query instanceof QueryExpression && $query->orderBy === [] && $query->limit === null && $query->with === null)) {
            $query = $query instanceof ParenthesizedQuery ? $query->query : $query->body;
        }
        if (!$query instanceof SetOperation || !$query->left instanceof Query || !$this->refers($query->right, $definition)) {
            return null;
        }
        $anchor = $this->planner->query($query->left, $outer);
        $domains = $this->planner->outputs($query);
        $names = $definition->columns === [] ? $anchor->names : array_map(static fn ($name): string => $name->value, $definition->columns);
        $working = new WorkingTable(count($domains));
        $id = spl_object_id($definition);
        $this->planner->recursions[$id] = [$working, $domains, $names];
        $recursive = $this->planner->query($query->right, $outer);
        unset($this->planner->recursions[$id]);
        $limit = (int) ($this->planner->compiler->connection->variables->read('cte_max_recursion_depth') ?? 1000);

        return new QueryPlan(new RecursiveUnion($anchor->root, $recursive->root, $working, $query->quantifier !== SetQuantifier::All, $domains, $limit), $domains, $names, $this->planner->materialized($domains));
    }

    /**
     * Tells whether a query reads a common table expression.
     */
    public function refers(Query $query, CommonTableExpression $definition): bool
    {
        foreach ((new Walker())->find($query, TableReference::class) as $reference) {
            $resolution = $this->planner->compiler->facts->relation($reference)->table;
            if ($resolution instanceof CommonTable && $resolution->definition === $definition) {
                return true;
            }
        }

        return false;
    }
}
