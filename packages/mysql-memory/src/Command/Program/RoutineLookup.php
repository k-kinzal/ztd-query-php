<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Program;

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
 * Determines whether SHOW ROUTINE STATUS fixes both parts of a routine identity.
 *
 * Equality of Db and Name to statement constants avoids materialized output columns, even if
 * the routine does not exist. LIKE does not supply an equality. Observed on MySQL 8.4.7.
 *
 * @visibility MySqlMemory
 */
final class RoutineLookup
{
    /**
     * Answers whether the filter fixes a database and routine name.
     */
    public static function single(ShowLike|ShowWhere|null $filter, Facts $facts): bool
    {
        if (!$filter instanceof ShowWhere) {
            return false;
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

        return isset($fixed['db'], $fixed['name']);
    }

    /**
     * Answers the unqualified column fixed to a statement constant, or an empty name.
     */
    public static function column(Scalar $column, Scalar $value, Facts $facts): string
    {
        return $column instanceof ColumnUse && $column->qualifier === null && Constancy::of($value, $facts) !== Constancy::Row ? strtolower($column->name->value) : '';
    }
}
