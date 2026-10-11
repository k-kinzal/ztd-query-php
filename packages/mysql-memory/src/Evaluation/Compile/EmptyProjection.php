<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Plan\Path\Transform\Aggregate as AggregatePath;
use MySqlMemory\Plan\Path\Transform\Filter;
use MySqlMemory\Plan\Path\Transform\Limit;
use MySqlMemory\Plan\Path\Transform\Project;
use MySqlMemory\Plan\Path\Transform\Sort;
use MySqlMemory\Plan\QueryPlan;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\JsonObjectAggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\DefaultOfColumn;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\InsertedColumn;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Shape\Field;

/**
 * Keeps row-subquery output expressions and initialized aggregate state readable without a result row.
 *
 * Null-safe row comparison can read these expressions after the subquery returns no row.
 * They must remain expressions: each comparison reads them in column order and can stop
 * before a later expression's warning or assignment (verified through SQL on MySQL 8.4).
 *
 * @visibility MySqlMemory\Evaluation
 */
final class EmptyProjection
{
    /**
     * @param Compiler $compiler The compiler of the statement
     */
    public function __construct(public readonly Compiler $compiler)
    {
    }

    /**
     * Answers a projection that reads independent expressions or aggregate state.
     *
     * @return list<Evaluable>
     */
    public function of(Query $query, QueryPlan $plan): array
    {
        while ($query instanceof ParenthesizedQuery || $query instanceof QueryExpression) {
            $query = $query instanceof ParenthesizedQuery ? $query->query : $query->body;
        }
        $path = $this->project($plan);
        if (!$query instanceof Select || $path === null) {
            return [];
        }
        $aggregated = $this->aggregate($plan) !== null;
        foreach ($this->compiler->facts->query($query)->projection as $field) {
            if (!$field instanceof Field || $field->expression === null || !$this->readable($field->expression, $aggregated)) {
                return [];
            }
        }

        return array_slice($path->expressions, 0, count($plan->domains));
    }

    /**
     * Finds the output projection through a query's ordering and row bounds.
     */
    public function project(QueryPlan $plan): ?Project
    {
        $path = $plan->root;
        while ($path instanceof Limit || $path instanceof Sort) {
            $path = $path->input;
        }

        return $path instanceof Project ? $path : null;
    }

    /**
     * Finds the aggregation whose output supplies the projection, before HAVING filters it.
     */
    public function aggregate(QueryPlan $plan): ?AggregatePath
    {
        $path = $this->project($plan)?->input;
        while ($path instanceof Filter) {
            $path = $path->input;
        }

        return $path instanceof AggregatePath ? $path : null;
    }

    /**
     * Answers whether an expression can read constants or initialized aggregate slots.
     * Aggregate arguments belong to the input phase and are not projection dependencies.
     */
    public function readable(Node $node, bool $aggregated): bool
    {
        if ($node instanceof Aggregate || $node instanceof GroupConcat || $node instanceof JsonObjectAggregate) {
            return $aggregated;
        }
        if ($node instanceof ColumnUse || $node instanceof WindowFunction || $node instanceof DefaultOfColumn || $node instanceof InsertedColumn || $node instanceof Query) {
            return false;
        }
        $children = [];
        $properties = get_object_vars($node);
        array_walk_recursive($properties, static function ($value) use (&$children): void {
            if ($value instanceof Node) {
                $children[] = $value;
            }
        });
        foreach ($children as $child) {
            if (!$this->readable($child, $aggregated)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Initializes aggregate output slots without evaluating any aggregate argument.
     *
     * @return list<int|float|string|null>
     */
    public function initial(QueryPlan $plan): array
    {
        $aggregate = $this->aggregate($plan);
        if ($aggregate === null) {
            return [];
        }
        $frame = new Frame($this->compiler->connection->context);
        $values = array_map(static fn (\MySqlMemory\Evaluation\Aggregate\Accumulation $value): int|float|string|null => $value->start()->result($frame), $aggregate->aggregates);

        return [...array_fill(0, $aggregate->input->width(), null), ...$values];
    }
}
