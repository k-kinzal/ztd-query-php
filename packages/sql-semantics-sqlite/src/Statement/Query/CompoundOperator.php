<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Query;

/**
 * The operators that combine the rows of two queries.
 *
 * Source: https://sqlite.org/lang_select.html#compound_select_statements.
 *
 * @visibility public
 * @example Reading the operator of a compound query
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT 1 UNION ALL SELECT 2');
 *     $query->statement->steps[0]->operator // => \SqlSemantics\Platform\Sqlite\Statement\Query\CompoundOperator::UnionAll
 */
enum CompoundOperator: string
{
    case Union = 'UNION';
    case UnionAll = 'UNION ALL';
    case Except = 'EXCEPT';
    case Intersect = 'INTERSECT';
}
