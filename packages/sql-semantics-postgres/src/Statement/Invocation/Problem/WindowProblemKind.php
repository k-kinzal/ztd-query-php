<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem;

/**
 * A window frame that its window's ordering cannot support.
 *
 * Each case carries the server's message.
 * Source: https://www.postgresql.org/docs/17/sql-expressions.html#SYNTAX-WINDOW-FUNCTIONS,
 * `transformWindowDefinitions` in `src/backend/parser/parse_clause.c` of PostgreSQL 17.
 *
 * @visibility public
 * @example Reading the message of a GROUPS frame without an ordering
 *     \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\WindowProblemKind::GroupsWithoutOrder->value // => 'GROUPS mode requires an ORDER BY clause'
 */
enum WindowProblemKind: string
{
    case RangeOffsetOrder = 'RANGE with offset PRECEDING/FOLLOWING requires exactly one ORDER BY column';
    case GroupsWithoutOrder = 'GROUPS mode requires an ORDER BY clause';
}
