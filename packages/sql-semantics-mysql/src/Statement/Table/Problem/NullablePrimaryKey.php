<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * A primary key column declared with the NULL attribute, which MySQL 5.7 and later reject (ER_PRIMARY_CANT_HAVE_NULL); MySQL 5.6 makes the column NOT NULL silently.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-indexes-keys.
 *
 * @visibility public
 * @example Reading the problem
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (a INT PRIMARY KEY NULL)')->facts->diagnostics[0]->column->value // => 'a'
 */
final class NullablePrimaryKey implements Diagnostic
{
    use Snapshot;

    /**
     * @param Name $column The column declared NULL
     */
    public function __construct(public readonly Name $column)
    {
    }

    /**
     * Describes the problem in the words of the server.
     */
    public function message(): string
    {
        return 'All parts of a PRIMARY KEY must be NOT NULL; column ' . $this->column->value . ' is declared NULL.';
    }
}
