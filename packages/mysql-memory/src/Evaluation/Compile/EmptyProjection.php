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
        if ($query instanceof \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation) {
            if (!(new Projection\EmptySet($this->compiler))->readable($query, $plan)) {
                return [];
            }

            return (new Projection\EmptySet($this->compiler))->projection($query, $plan);
        }
        $path = $this->project($plan);
        if (!$query instanceof Select || $path === null) {
            return [];
        }
        $aggregated = $this->aggregate($plan) !== null;
        $source = $this->source($plan);
        $expressions = [];
        foreach ($query->queryInformation()->projection as $field) {
            if (!$field instanceof Field || $field->expression === null || !$this->readable($field->expression, $aggregated, $source !== null)) {
                return [];
            }
            $expressions[] = $this->retain($path->expressions[$field->position], $field->expression);
        }

        return $expressions;
    }

    /**
     * Retains legacy empty projections independently of their comparison state.
     * MySQL 5.6 preserves computed sources for NULL inspection; direct column slots clear on reuse.
     * Variable reads retain typed sources on both legacy releases; assignments do so on MySQL 5.6.
     */
    public function retain(Evaluable $value, \SqlSemantics\Statement\Scalar $node): Evaluable
    {
        if (!$this->compiler->settings->legacy()) {
            return $value;
        }
        while (($replacement = $node->scalarInformation()->replacement) !== null) {
            $node = $replacement;
        }
        $constant = $this->compiler->constancy($node) === Constancy::Resolved;
        $old = $this->compiler->settings->release() === \SqlSemantics\Contract\GrammarRelease::MySql5651;
        if ($old && $constant && $value->domain()->kind === \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Date) {
            return new \MySqlMemory\Evaluation\Subquery\CachedProjection($value, true, true);
        }
        if ($old && !in_array($value->domain()->kind, [\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Integer, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Decimal, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Double, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::String], true)) {
            return $value;
        }
        $direct = $node->scalarInformation()->resolution instanceof \SqlSemantics\Statement\Reference\Column\ResolvedColumn;

        return new \MySqlMemory\Evaluation\Subquery\CachedProjection($value, $constant || $node instanceof \SqlSemantics\Platform\MySql\Statement\Variable\UserVariable || ($old && !$direct));
    }

    /**
     * Finds the output projection through a query's ordering and row bounds.
     */
    public function project(QueryPlan $plan): ?Project
    {
        $path = $plan->root;
        while ($path instanceof Limit || $path instanceof Sort || $path instanceof \MySqlMemory\Plan\Path\Transform\Prepare) {
            $path = $path->input;
        }

        return $path instanceof Project ? $path : null;
    }

    /**
     * Finds a sole base-table source whose buffered fields have the projection's column layout.
     */
    public function source(QueryPlan $plan): ?\MySqlMemory\Plan\Path\Source\TableScan
    {
        $path = $this->project($plan)?->input;
        while ($path instanceof Filter || $path instanceof Limit || $path instanceof Sort || ($path instanceof Project && $path->append) || $path instanceof \MySqlMemory\Plan\Path\Transform\Lock || $path instanceof \MySqlMemory\Plan\Path\Transform\Window) {
            $path = $path->input;
        }
        if ($path instanceof \MySqlMemory\Plan\Path\Source\KeyLookup) {
            $path = $path->input;
        }

        return $path instanceof \MySqlMemory\Plan\Path\Source\TableScan ? $path : null;
    }

    /**
     * Finds the aggregation whose output supplies the projection, before HAVING filters it.
     */
    public function aggregate(QueryPlan $plan): ?AggregatePath
    {
        $path = $this->project($plan)?->input;
        while ($path instanceof Filter || $path instanceof Limit || $path instanceof Sort || ($path instanceof Project && $path->append) || $path instanceof \MySqlMemory\Plan\Path\Transform\Window) {
            $path = $path->input;
        }

        return $path instanceof AggregatePath ? $path : null;
    }

    /**
     * Answers whether an expression can read constants, outer rows, program variables or initialized aggregate slots.
     * Aggregate arguments belong to the input phase and are not projection dependencies.
     * Nested queries own their input iterators and remain lazy projection expressions.
     */
    public function readable(Node $node, bool $aggregated, bool $tabled = false): bool
    {
        if ($node instanceof Aggregate || $node instanceof GroupConcat || $node instanceof JsonObjectAggregate) {
            return $node->over !== null || $aggregated;
        }
        if ($node instanceof ColumnUse) {
            $column = $node->hasBinding() ? $node->scalarInformation()->resolution : null;
            if ($column instanceof \SqlSemantics\Statement\Reference\Column\AliasTarget) {
                return $column->depth > 0;
            }

            return $column instanceof \SqlSemantics\Statement\Reference\Column\ResolvedColumn && ($column->depth > 0 || $tabled || $this->compiler->connection->program?->find($column->relation, $node->name->value) !== null);
        }
        if ($node instanceof Query || $node instanceof DefaultOfColumn) {
            return true;
        }
        if ($node instanceof WindowFunction) {
            return true;
        }
        if ($node instanceof InsertedColumn) {
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
            if (!$this->readable($child, $aggregated, $tabled)) {
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
