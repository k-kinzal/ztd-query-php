<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Problem;

use SqlSemantics\Platform\MySql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A call of a native function with an argument that has an alias, which only loadable functions accept (ER_WRONG_PARAMETERS_TO_NATIVE_FCT).
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/udf-arguments.html,
 * https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html#error_er_wrong_parameters_to_native_fct.
 *
 * @visibility SqlSemantics
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
