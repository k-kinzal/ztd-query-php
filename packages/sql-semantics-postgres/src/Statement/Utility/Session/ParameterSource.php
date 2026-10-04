<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Session;

/**
 * Where a SET without a written value takes the value from.
 *
 * `SET name TO DEFAULT` restores the default of the parameter, as RESET
 * does; `SET name FROM CURRENT` saves the value in effect when the command
 * runs, which is meaningful for the setting of a routine, role or database.
 * Mirrors `VAR_SET_DEFAULT` and `VAR_SET_CURRENT` of PostgreSQL's `VariableSetKind`.
 * Source: https://www.postgresql.org/docs/17/sql-set.html, https://www.postgresql.org/docs/17/sql-createfunction.html.
 *
 * @visibility public
 * @example Reading the source of a SET ... FROM CURRENT
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SET search_path FROM CURRENT');
 *     $operation->statement->source // => \SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\ParameterSource::Current
 */
enum ParameterSource
{
    case Default;
    case Current;
}
