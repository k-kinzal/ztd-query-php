<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Structure;

use SplObjectStorage;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\ProcedureAnalyse;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;

/**
 * Enforces the query grammar shared by CREATE VIEW and ALTER VIEW.
 *
 * A view query cannot invoke the legacy PROCEDURE ANALYSE result processor.
 * The restriction applies through query wrappers and set operations.
 * Source: https://dev.mysql.com/doc/refman/5.7/en/create-view.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ViewQueries
{
    /**
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When a view query holds PROCEDURE ANALYSE
     */
    public function check(Query $query): void
    {
        $pending = [$query];
        $seen = new SplObjectStorage();
        while ($pending !== []) {
            $value = array_pop($pending);
            if (is_array($value)) {
                array_push($pending, ...array_values($value));
            } elseif ($value instanceof Node && !isset($seen[$value])) {
                $seen[$value] = true;
                Check::input(!$value instanceof ProcedureAnalyse, 'A view query cannot invoke PROCEDURE ANALYSE.');
                array_push($pending, ...array_values(get_object_vars($value)));
            }
        }
    }
}
