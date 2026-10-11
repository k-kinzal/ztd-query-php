<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile;

use MySqlMemory\Evaluation\Evaluable;
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
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Shape\Field;

/**
 * Compiles row-subquery output expressions that can be read without an input row.
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
     * Answers an independently readable projection, or none when it needs input state.
     *
     * @return list<Evaluable>
     */
    public function of(Query $query, QueryPlan $plan): array
    {
        while ($query instanceof ParenthesizedQuery || $query instanceof QueryExpression) {
            $query = $query instanceof ParenthesizedQuery ? $query->query : $query->body;
        }
        if (!$query instanceof Select) {
            return [];
        }
        foreach ($this->compiler->facts->query($query)->projection as $field) {
            if (!$field instanceof Field || $field->expression === null) {
                return [];
            }
            foreach ([ColumnUse::class, Aggregate::class, GroupConcat::class, JsonObjectAggregate::class, WindowFunction::class, DefaultOfColumn::class, InsertedColumn::class, Query::class] as $dependency) {
                if ((new Walker())->find($field->expression, $dependency) !== []) {
                    return [];
                }
            }
        }
        $path = $plan->root;
        while ($path instanceof Limit || $path instanceof Sort) {
            $path = $path->input;
        }

        return $path instanceof Project ? array_slice($path->expressions, 0, count($plan->domains)) : [];
    }
}
