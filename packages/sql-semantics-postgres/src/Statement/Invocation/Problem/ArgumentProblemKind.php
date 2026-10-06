<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem;

/**
 * A mistake in how the arguments of a call are named.
 *
 * Source: https://www.postgresql.org/docs/17/sql-syntax-calling-funcs.html#SQL-SYNTAX-CALLING-FUNCS-MIXED.
 *
 * @visibility public
 * @example Reading the message of a repeated parameter name
 *     \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\ArgumentProblemKind::RepeatedName->value // => 'argument name "%s" used more than once'
 */
enum ArgumentProblemKind: string
{
    case PositionalAfterNamed = 'positional argument cannot follow named argument';
    case RepeatedName = 'argument name "%s" used more than once';
}
