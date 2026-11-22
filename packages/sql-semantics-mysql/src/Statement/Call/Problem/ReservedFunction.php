<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Problem;

use SqlSemantics\Platform\MySql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A call of a native function the server reserves for the views of its data dictionary (ER_NO_ACCESS_TO_NATIVE_FCT).
 *
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html#error_er_no_access_to_native_fct.
 *
 * @visibility SqlSemantics
 */
final class ReservedFunction implements Diagnostic
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
        return "Access to native function '" . $this->function->value . "' is rejected";
    }
}
