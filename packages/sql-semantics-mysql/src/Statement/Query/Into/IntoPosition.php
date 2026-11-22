<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Into;

/**
 * Where an INTO clause is written in a query.
 *
 * MySQL accepts INTO right after the select list, after the clauses of the
 * query (before a locking clause), or after the locking clauses; the
 * position does not change the meaning and is kept as written.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/select-into.html.
 *
 * @visibility SqlSemantics
 */
enum IntoPosition
{
    case AfterItems;
    case AfterQuery;
    case AfterLocking;
}
