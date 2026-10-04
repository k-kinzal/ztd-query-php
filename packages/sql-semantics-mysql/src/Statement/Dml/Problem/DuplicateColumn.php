<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * A column an INSERT or REPLACE names twice among the columns it writes.
 *
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html (ER_FIELD_SPECIFIED_TWICE).
 *
 * @visibility public
 * @example Reading the column written twice
 *     $insert = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('INSERT INTO t (a, a) VALUES (1, 2)');
 *     $insert->facts->diagnostics[0]->message() // => "Column 'a' specified twice"
 */
final class DuplicateColumn implements Diagnostic
{
    use Snapshot;

    /**
     * @param Name $column The column named again
     */
    public function __construct(public readonly Name $column)
    {
    }

    /**
     * Describes the problem in the words of the server.
     */
    public function message(): string
    {
        return "Column '" . $this->column->value . "' specified twice";
    }
}
