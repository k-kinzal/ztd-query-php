<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Variable\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A system variable written with an instance name that is not a structured variable (`ER_VARIABLE_IS_NOT_STRUCT`, error 1272), which MySQL 5.6 and 5.7 report for `@@instance.name`.
 *
 * Those releases read the name after the instance as the variable, so a
 * variable they know without being a component of a key cache is refused,
 * and one they do not know is unknown by that name (verified on live 5.6.51
 * and 5.7.44 servers).
 * Source: https://dev.mysql.com/doc/refman/5.7/en/structured-system-variables.html.
 *
 * @visibility public
 * @example Reading a variable that is not structured with an instance in MySQL 5.7
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-5.7.44'))->analyze('SELECT @@x.sql_mode');
 *     $query->facts->diagnostics[0]->message() // => "Variable 'sql_mode' is not a variable component (can't be used as XXXX.variable_name)"
 */
final class UnstructuredVariable implements Diagnostic
{
    use Snapshot;

    /**
     * @param string $name The variable as written after the instance
     */
    public function __construct(public readonly string $name)
    {
    }

    /**
     * Describes the problem as the server does.
     */
    public function message(): string
    {
        return sprintf("Variable '%s' is not a variable component (can't be used as XXXX.variable_name)", $this->name);
    }
}
