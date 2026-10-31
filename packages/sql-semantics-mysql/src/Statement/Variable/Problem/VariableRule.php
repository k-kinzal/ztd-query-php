<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Variable\Problem;

/**
 * The rule of system variables a read or an assignment breaks.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/using-system-variables.html.
 *
 * @visibility public
 * @example The error of an assignment to a read-only variable
 *     \SqlSemantics\Platform\MySql\Statement\Variable\Problem\VariableRule::ReadOnly->code() // => 1238
 */
enum VariableRule
{
    case GlobalOfSessionVariable;
    case SessionOfGlobalVariable;
    case ReadOnly;
    case SetGlobalOfSessionVariable;
    case SetSessionOfGlobalVariable;
    case SessionReadOnly;

    /**
     * Answers the error number the server reports.
     */
    public function code(): int
    {
        return match ($this) {
            self::GlobalOfSessionVariable, self::SessionOfGlobalVariable, self::ReadOnly => 1238,
            self::SetGlobalOfSessionVariable => 1228,
            self::SetSessionOfGlobalVariable => 1229,
            self::SessionReadOnly => 1621,
        };
    }
}
