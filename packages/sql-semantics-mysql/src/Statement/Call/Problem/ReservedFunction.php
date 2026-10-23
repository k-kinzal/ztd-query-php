<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * A call of a native function the server reserves for the views of its data dictionary (ER_NO_ACCESS_TO_NATIVE_FCT).
 *
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html#error_er_no_access_to_native_fct.
 *
 * @visibility public
 * @example Reading the problem of a reserved function
 *     (new \SqlSemantics\Platform\MySql\Statement\Call\Problem\ReservedFunction(new \SqlSemantics\Statement\Identifier\Name('internal_table_rows')))->message() // => "Access to native function 'internal_table_rows' is rejected"
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
