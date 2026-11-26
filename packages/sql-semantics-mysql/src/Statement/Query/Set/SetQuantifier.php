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
 * @visibility SqlSemantics
 */
enum SetQuantifier: string
{
    case Distinct = 'DISTINCT';
    case All = 'ALL';
}
