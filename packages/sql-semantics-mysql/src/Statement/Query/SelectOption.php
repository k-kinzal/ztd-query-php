<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query;

/**
 * A modifier written between SELECT and the select list.
 *
 * Each case holds the keyword it is written with. The modifiers are kept in
 * written order; MySQL rejects contradictory ones such as DISTINCT with ALL.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/select.html,
 * https://dev.mysql.com/doc/refman/5.7/en/query-cache-in-select.html (SQL_CACHE, SQL_NO_CACHE).
 *
 * @visibility public
 * @example Reading the modifiers of a selection
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT DISTINCT SQL_BIG_RESULT a FROM t');
 *     $query->statement->options // => [\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::Distinct, \SqlSemantics\Platform\MySql\Statement\Query\SelectOption::BigResult]
 */
enum SelectOption: string
{
    case All = 'ALL';
    case Distinct = 'DISTINCT';
    case StraightJoin = 'STRAIGHT_JOIN';
    case HighPriority = 'HIGH_PRIORITY';
    case SmallResult = 'SQL_SMALL_RESULT';
    case BigResult = 'SQL_BIG_RESULT';
    case BufferResult = 'SQL_BUFFER_RESULT';
    case CalcFoundRows = 'SQL_CALC_FOUND_ROWS';
    case NoCache = 'SQL_NO_CACHE';
    case Cache = 'SQL_CACHE';
}
