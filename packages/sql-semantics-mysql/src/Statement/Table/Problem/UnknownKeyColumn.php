<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * A key part that names no column of the table (ER_KEY_COLUMN_DOES_NOT_EXITS).
 *
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html.
 *
 * @visibility public
 * @example Reading the problem
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (a INT, KEY (b))')->facts->diagnostics[0]->column->value // => 'b'
 */
final class UnknownKeyColumn implements Diagnostic
{
    use Snapshot;

    /**
     * @param Name $column The column name the key part writes
     */
    public function __construct(public readonly Name $column)
    {
    }

    /**
     * Describes the problem in the words of the server.
     */
    public function message(): string
    {
        return 'Key column ' . $this->column->value . " doesn't exist in table.";
    }
}
