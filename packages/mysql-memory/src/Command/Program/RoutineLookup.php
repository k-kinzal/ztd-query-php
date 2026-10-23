<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Program;

use MySqlMemory\Command\Show\FixedColumns;
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
        $fixed = FixedColumns::of($filter, $facts);

        return isset($fixed['db'], $fixed['name']);
    }

    /**
     * Answers the unqualified column fixed to a statement constant, or an empty name.
     */
    public static function column(Scalar $column, Scalar $value, Facts $facts): string
    {
        return FixedColumns::column($column, $value, $facts);
    }
}
