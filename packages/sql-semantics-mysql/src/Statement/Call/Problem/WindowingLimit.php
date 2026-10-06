<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Problem;

/**
 * The parts of the window function syntax the grammar accepts and the server rejects as not supported (ER_NOT_SUPPORTED_YET).
 *
 * Each case holds the words the server names the feature with.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/window-function-restrictions.html.
 *
 * @visibility public
 * @example Reading the words of a case
 *     \SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowingLimit::IgnoreNulls->value // => 'IGNORE NULLS'
 */
enum WindowingLimit: string
{
    case GroupsUnit = 'GROUPS';
    case Exclusion = 'EXCLUDE';
    case IgnoreNulls = 'IGNORE NULLS';
    case FromLast = 'FROM LAST';
    case DistinctAggregate = '<window function>(DISTINCT ..)';
    case GroupConcat = 'GROUP_CONCAT as window function';
}
