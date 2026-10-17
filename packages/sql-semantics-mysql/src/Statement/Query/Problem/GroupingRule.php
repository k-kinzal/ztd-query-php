<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Problem;

/**
 * The rule of ONLY_FULL_GROUP_BY a column breaks.
 *
 * NotDetermined: a grouped query reads a column its GROUP BY does not determine
 * (ER_WRONG_FIELD_WITH_GROUP). WithoutGroupBy: an aggregated query without GROUP BY selects a
 * column outside an aggregate (ER_MIX_OF_GROUP_FUNC_AND_FIELDS). NotSelected: a DISTINCT query
 * orders by a column it does not select (ER_FIELD_IN_ORDER_NOT_SELECT).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/group-by-handling.html.
 *
 * @visibility public
 * @example Reading the rule of a DISTINCT query
 *     \SqlSemantics\Platform\MySql\Statement\Query\Problem\GroupingRule::NotSelected->name // => 'NotSelected'
 */
enum GroupingRule
{
    case NotDetermined;
    case WithoutGroupBy;
    case NotSelected;
}
