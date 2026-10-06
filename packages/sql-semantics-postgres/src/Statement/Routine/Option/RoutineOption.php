<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Routine\Option;

use SqlSemantics\Platform\PostgreSql\Statement\Clause;

/**
 * One option of a routine definition or alteration, such as `IMMUTABLE`, `LANGUAGE sql` or `SET search_path = x`.
 *
 * Mirrors the `DefElem` items PostgreSQL builds for `createfunc_opt_item` and
 * `common_func_opt_item`. Each option sets one attribute of the routine; the
 * attribute it sets is its setting, and an attribute may be set once per
 * statement, except configuration settings.
 * Source: https://www.postgresql.org/docs/17/sql-createfunction.html.
 *
 * @visibility public
 * @example Telling that an attribute keyword is a routine option
 *     \SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\RoutineAttribute::Immutable instanceof \SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\RoutineOption // => true
 */
interface RoutineOption extends Clause
{
    /**
     * Tells whether ALTER FUNCTION, PROCEDURE and ROUTINE accept the option too.
     */
    public function alterable(): bool;

    /**
     * Answers the attribute the option sets, as the server names it, or null when the option may be repeated.
     */
    public function setting(): ?string;
}
