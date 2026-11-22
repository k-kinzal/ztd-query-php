<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Relation\Hint;

/**
 * The part of query processing an index hint applies to: `FOR JOIN`, `FOR ORDER BY` or `FOR GROUP BY`.
 *
 * Without a scope the hint applies to all of them; the absence is kept as
 * written.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/index-hints.html.
 *
 * @visibility SqlSemantics
 */
enum IndexHintScope
{
    case Join;
    case OrderBy;
    case GroupBy;
}
