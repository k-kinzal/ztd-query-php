<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Show;

use MySqlMemory\Evaluation\Compile\Constancy;
use MySqlMemory\Plan\ConstantTables;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\InList;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowLike;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowWhere;
use SqlSemantics\Statement\Fact\Facts;
use SqlSemantics\Statement\Scalar;

/**
 * Finds SHOW columns constrained to statement constants by conjunctive equality predicates.
 * A singleton IN list supplies the same fact; LIKE and disjunctions do not.
 *
 * @visibility MySqlMemory
 */
final class FixedColumns
{
    /**
     * Answers the lower-case names fixed by the filter, without evaluating its expressions.
     *
     * @return array<string, true>
     */
    public static function of(ShowLike|ShowWhere|null $filter, Facts $facts): array
    {
        if (!$filter instanceof ShowWhere) {
            return [];
        }
        $fixed = [];
        $tables = new ConstantTables($facts);
        foreach ($tables->conjuncts($filter->condition) as $condition) {
            if ($condition instanceof Comparison && in_array($condition->operator, [ComparisonOperator::Equal, ComparisonOperator::NullSafeEqual], true)) {
                $fixed[self::column($tables->unwrap($condition->left), $condition->right, $facts)] = true;
                $fixed[self::column($tables->unwrap($condition->right), $condition->left, $facts)] = true;
            }
            if ($condition instanceof InList && !$condition->negated && count($condition->elements) === 1) {
                $fixed[self::column($tables->unwrap($condition->operand), $condition->elements[0], $facts)] = true;
            }
        }
        unset($fixed['']);

        return $fixed;
    }

    /**
     * Answers the unqualified column fixed to a statement constant, or an empty name.
     */
    public static function column(Scalar $column, Scalar $value, Facts $facts): string
    {
        return $column instanceof ColumnUse && $column->qualifier === null && Constancy::of($value, $facts) !== Constancy::Row ? strtolower($column->name->value) : '';
    }
}
