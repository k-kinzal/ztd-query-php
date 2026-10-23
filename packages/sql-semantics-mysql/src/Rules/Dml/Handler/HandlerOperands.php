<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Dml\Handler;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;

/**
 * Constrains HANDLER expressions independently of open handles and tables.
 *
 * Subqueries have no interpretation in this command, even in an unselected
 * branch. Source: https://dev.mysql.com/doc/refman/8.4/en/handler.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class HandlerOperands
{
    /**
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When a read contains a subquery
     */
    public function check(Node $root): void
    {
        $pending = [$root];
        while ($pending !== []) {
            $value = array_pop($pending);
            if (is_array($value)) {
                array_push($pending, ...array_values($value));
            } elseif ($value instanceof Node) {
                Check::input(!$value instanceof Query, 'A HANDLER read cannot contain a subquery.');
                array_push($pending, ...array_values(get_object_vars($value)));
            }
        }
    }
}
