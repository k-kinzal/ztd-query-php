<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Problem;

use SqlSemantics\Platform\MySql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A column of a created table or an alias of a view column that is not a valid column name (ER_WRONG_COLUMN_NAME).
 *
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html#error_er_wrong_column_name.
 *
 * @visibility SqlSemantics
 */
final class IncorrectColumnName implements Diagnostic
{
    use Snapshot;

    /**
     * @param Name $column The rejected name
     */
    public function __construct(public readonly Name $column)
    {
    }

    /**
     * Describes the problem in the words of the server.
     */
    public function message(): string
    {
        return "Incorrect column name '" . $this->column->value . "'.";
    }
}
