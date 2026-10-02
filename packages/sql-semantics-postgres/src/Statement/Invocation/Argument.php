<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation;

use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;

/**
 * One argument of a function call: a value, optionally passed by parameter name.
 *
 * Mirrors `func_arg_expr`: positional `value`, and named `name => value` or
 * the older `name := value`.
 * Source: https://www.postgresql.org/docs/17/sql-syntax-calling-funcs.html.
 *
 * @visibility public
 * @example Naming the contract a call argument fulfils
 *     is_subclass_of(\SqlSemantics\Platform\PostgreSql\Statement\Invocation\Argument::class, \SqlSemantics\Platform\PostgreSql\Statement\Clause::class) // => true
 */
interface Argument extends Clause
{
    /**
     * Answers the argument value.
     */
    public function value(): Scalar;

    /**
     * Answers the parameter name the value is passed by, or null for a positional argument.
     */
    public function name(): ?Name;
}
