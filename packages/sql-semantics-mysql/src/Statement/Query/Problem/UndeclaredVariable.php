<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * A bare name read as a variable of a stored program in a statement that no stored program holds.
 *
 * Only a stored program declares such variables, so outside one the name names none.
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html#error_er_sp_undeclared_var.
 *
 * @visibility public
 * @example Reading the name of a LIMIT operand no program declares
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT 1 LIMIT n');
 *     $query->facts->diagnostics[0]->name->value // => 'n'
 */
final class UndeclaredVariable implements Diagnostic
{
    use Snapshot;

    /**
     * @param Name $name The variable name as written
     */
    public function __construct(public readonly Name $name)
    {
    }

    /**
     * Describes the problem in the words of the server.
     */
    public function message(): string
    {
        return 'Undeclared variable: ' . $this->name->value;
    }
}
