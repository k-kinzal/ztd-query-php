<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Variable\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A system variable read or assigned in a scope it does not have, or assigned while read-only.
 *
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html#error_er_incorrect_global_local_var.
 *
 * @visibility public
 * @example Reading the global value of a session variable
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT @@GLOBAL.timestamp');
 *     $query->facts->diagnostics[0]->message() // => "Variable 'timestamp' is a SESSION variable"
 */
final class VariableMisuse implements Diagnostic
{
    use Snapshot;

    /**
     * @param VariableRule $rule The rule broken
     * @param string $name The variable as written
     */
    public function __construct(public readonly VariableRule $rule, public readonly string $name)
    {
    }

    /**
     * Describes the problem as the server does.
     */
    public function message(): string
    {
        return match ($this->rule) {
            VariableRule::GlobalOfSessionVariable => sprintf("Variable '%s' is a SESSION variable", $this->name),
            VariableRule::SessionOfGlobalVariable => sprintf("Variable '%s' is a GLOBAL variable", $this->name),
            VariableRule::ReadOnly => sprintf("Variable '%s' is a read only variable", $this->name),
            VariableRule::SetGlobalOfSessionVariable => sprintf("Variable '%s' is a SESSION variable and can't be used with SET GLOBAL", $this->name),
            VariableRule::SetSessionOfGlobalVariable => sprintf("Variable '%s' is a GLOBAL variable and should be set with SET GLOBAL", $this->name),
            VariableRule::SessionReadOnly => sprintf("SESSION variable '%s' is read-only. Use SET GLOBAL to assign the value", $this->name),
        };
    }
}
