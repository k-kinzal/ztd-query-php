<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Query;

/**
 * The operators that combine the rows of two queries.
 *
 * Source: https://sqlite.org/lang_select.html#compound_select_statements.
 *
 * @visibility SqlSemantics
 */
enum CompoundOperator: string
{
    case Union = 'UNION';
    case UnionAll = 'UNION ALL';
    case Except = 'EXCEPT';
    case Intersect = 'INTERSECT';
}
