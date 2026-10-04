<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Conditional;

/**
 * Which extreme GREATEST or LEAST selects.
 *
 * Mirrors PostgreSQL's `MinMaxOp`.
 * Source: https://www.postgresql.org/docs/17/functions-conditional.html#FUNCTIONS-GREATEST-LEAST.
 *
 * @visibility public
 * @example Spelling the smallest-value function
 *     \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Conditional\MinMaxKind::Least->value // => 'LEAST'
 */
enum MinMaxKind: string
{
    case Greatest = 'GREATEST';
    case Least = 'LEAST';
}
