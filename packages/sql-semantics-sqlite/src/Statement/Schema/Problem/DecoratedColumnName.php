<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Problem;

use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A column name in a plain column list that is written with a collation or a sort order.
 *
 * The grammar shares one production between indexed column lists and plain
 * column lists; SQLite rejects the decoration in the column list of a foreign
 * key, of a parent key and of a view with "syntax error after column name".
 * Source: https://sqlite.org/syntax/foreign-key-clause.html.
 *
 * @visibility SqlSemantics
 */
final class DecoratedColumnName implements Diagnostic
{
    use Snapshot;

    /**
     * @param Name $column The decorated column name
     */
    public function __construct(public readonly Name $column)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return 'Column name ' . $this->column->value . ' of a column list is written with a collation or a sort order.';
    }
}
