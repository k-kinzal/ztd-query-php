<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * A call of a native function with a number of arguments the function does not accept (ER_WRONG_PARAMCOUNT_TO_NATIVE_FCT).
 *
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html#error_er_wrong_paramcount_to_native_fct.
 *
 * @visibility public
 * @example Reading the problem of a call with too many arguments
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT abs(1, 2)');
 *     $query->facts->diagnostics[0]->message() // => "Incorrect parameter count in the call to native function 'abs'"
 */
final class WrongArgumentCount implements Diagnostic
{
    use Snapshot;

    /**
     * @param Name $function The function name as written
     * @param int $arguments The number of arguments supplied
     */
    public function __construct(public readonly Name $function, public readonly int $arguments)
    {
    }

    /**
     * Describes the problem in the words of the server.
     */
    public function message(): string
    {
        return "Incorrect parameter count in the call to native function '" . $this->function->value . "'";
    }
}
