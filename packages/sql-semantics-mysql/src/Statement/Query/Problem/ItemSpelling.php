<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Problem;

use SqlSemantics\Statement\Reference\Missing\MissingInput;
use SqlSemantics\Statement\Snapshot;

/**
 * The source text of a select list expression, which MySQL uses as the column name and the model does not keep.
 *
 * Rule: MYSQL-SELECT-ITEM-NAME-001. An item without alias that is no column
 * reference is named after the text of its expression as written. The
 * model keeps the meaning of the expression and not its text, so the name
 * of such a column of a derived table, a common table or a view is not
 * fixed, and a lookup that could only match such a column depends on this
 * input. Source: https://dev.mysql.com/doc/refman/8.4/en/select.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading why a column of a derived table cannot be decided by name
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT x FROM (SELECT 1 + 1) AS d', []);
 *     $query->field('x')->type->missing[0]->describe() // => 'the source text MySQL names an unaliased select list expression after'
 */
final class ItemSpelling implements MissingInput
{
    use Snapshot;

    /**
     * Describes the missing input.
     */
    public function describe(): string
    {
        return 'the source text MySQL names an unaliased select list expression after';
    }
}
