<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * A call of a native function with an argument that has an alias, which only loadable functions accept (ER_WRONG_PARAMETERS_TO_NATIVE_FCT).
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/udf-arguments.html,
 * https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html#error_er_wrong_parameters_to_native_fct.
 *
 * @visibility public
 * @example Reading the problem of a named argument
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT abs(1 AS x)');
 *     $query->facts->diagnostics[0]->message() // => "Incorrect parameters in the call to native function 'abs'"
 */
final class NamedArgument implements Diagnostic
{
    use Snapshot;

    /**
     * @param Name $function The function name as written
     */
    public function __construct(public readonly Name $function)
    {
    }

    /**
     * Describes the problem in the words of the server.
     */
    public function message(): string
    {
        return "Incorrect parameters in the call to native function '" . $this->function->value . "'";
    }
}
