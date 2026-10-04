<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem;

/**
 * A clause of a call that the kind of the function called does not accept.
 *
 * Each case carries the server's message, with `%s` for the function name.
 * Source: https://www.postgresql.org/docs/17/sql-expressions.html#SYNTAX-AGGREGATES,
 * https://www.postgresql.org/docs/17/sql-expressions.html#SYNTAX-WINDOW-FUNCTIONS,
 * `ParseFuncOrColumn` in `src/backend/parser/parse_func.c` of PostgreSQL 17.
 *
 * @visibility public
 * @example Reading the message of a filter on a plain function
 *     \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\CallMisuseKind::FilterOnPlainFunction->value // => 'FILTER specified, but %s is not an aggregate function'
 */
enum CallMisuseKind: string
{
    case StarOnPlainFunction = '%s(*) specified, but %s is not an aggregate function';
    case DistinctOnPlainFunction = 'DISTINCT specified, but %s is not an aggregate function';
    case WithinGroupOnPlainFunction = 'WITHIN GROUP specified, but %s is not an aggregate function';
    case OrderOnPlainFunction = 'ORDER BY specified, but %s is not an aggregate function';
    case FilterOnPlainFunction = 'FILTER specified, but %s is not an aggregate function';
    case OverOnPlainFunction = 'OVER specified, but %s is not a window function nor an aggregate function';
    case WithinGroupOnPlainAggregate = '%s is not an ordered-set aggregate, so it cannot have WITHIN GROUP';
    case ParameterlessWithoutStar = '%s(*) must be used to call a parameterless aggregate function';
    case WindowWithoutOver = 'window function %s requires an OVER clause';
    case WithinGroupOnWindow = 'window function %s cannot have WITHIN GROUP';
    case DistinctInWindow = 'DISTINCT is not implemented for window functions';
    case OrderInWindow = 'aggregate ORDER BY is not implemented for window functions';
    case FilterOnWindow = 'FILTER is not implemented for non-aggregate window functions';
}
