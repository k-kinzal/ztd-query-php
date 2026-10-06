<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Set;

/**
 * The DISTINCT or ALL written after a set operator.
 *
 * Without a quantifier a set operation removes duplicate rows, as with
 * DISTINCT; the absence is kept as written.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/set-operations.html.
 *
 * @visibility public
 * @example Reading the quantifier of a union
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT 1 UNION ALL SELECT 2');
 *     $query->statement->quantifier // => \SqlSemantics\Platform\MySql\Statement\Query\Set\SetQuantifier::All
 */
enum SetQuantifier: string
{
    case Distinct = 'DISTINCT';
    case All = 'ALL';
}
