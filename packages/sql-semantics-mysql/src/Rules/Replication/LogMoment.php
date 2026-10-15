<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Replication;

use SplObjectStorage;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Scalar;

/**
 * Constrains a PURGE moment independently of the connection and binary logs.
 *
 * A query cannot occur anywhere in this expression, including an unselected
 * CASE arm. Native MySQL 5.6.51, 5.7.44, 8.0.44, 8.4.7 and 9.1.0 also refuse
 * these operands when defining a procedure, before its body can execute.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/purge-binary-logs.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class LogMoment
{
    /**
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When the expression contains a query
     */
    public function check(Scalar $moment): void
    {
        $pending = [$moment];
        $seen = new SplObjectStorage();
        while ($pending !== []) {
            $value = array_pop($pending);
            if (is_array($value)) {
                array_push($pending, ...array_values($value));
            } elseif ($value instanceof Node && !isset($seen[$value])) {
                $seen[$value] = true;
                Check::input(!$value instanceof Query, 'A PURGE moment cannot contain a subquery.');
                array_push($pending, ...array_values(get_object_vars($value)));
            }
        }
    }
}
