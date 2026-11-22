<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Problem;

use SqlSemantics\Platform\MySql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A table or view that would have two columns with one name (ER_DUP_FIELDNAME).
 *
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html.
 *
 * @visibility SqlSemantics
 */
final class DuplicateColumn implements Diagnostic
{
    use Snapshot;

    /**
     * @param Name $column The repeated column name
     */
    public function __construct(public readonly Name $column)
    {
    }

    /**
     * Describes the problem in the words of the server.
     */
    public function message(): string
    {
        return 'Duplicate column name ' . $this->column->value . '.';
    }
}
