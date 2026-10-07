<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Variable\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A system variable the release does not have (`ER_UNKNOWN_SYSTEM_VARIABLE`, error 1193).
 *
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html#error_er_unknown_system_variable.
 *
 * @visibility public
 * @example Reading a variable that does not exist
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT @@nosuch');
 *     $query->facts->diagnostics[0]->message() // => "Unknown system variable 'nosuch'"
 */
final class UnknownSystemVariable implements Diagnostic
{
    use Snapshot;

    /**
     * @param string $name The variable as written
     */
    public function __construct(public readonly string $name)
    {
    }

    /**
     * Describes the problem as the server does.
     */
    public function message(): string
    {
        return sprintf("Unknown system variable '%s'", $this->name);
    }
}
