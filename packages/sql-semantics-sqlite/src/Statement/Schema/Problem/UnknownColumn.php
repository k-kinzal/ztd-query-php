<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Problem;

use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A column name in a definition or alteration that the completely known table does not have.
 *
 * It is reported for the child columns of a foreign key and for the column
 * that ALTER TABLE drops or renames. A name inside an expression is reported
 * by the expression itself as a missing column.
 * Source: https://sqlite.org/lang_altertable.html, https://sqlite.org/foreignkeys.html.
 *
 * @visibility SqlSemantics
 */
final class UnknownColumn implements Diagnostic
{
    use Snapshot;

    /**
     * @param Name $column The name that denotes no column
     */
    public function __construct(public readonly Name $column)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return 'Column ' . $this->column->value . ' does not exist in the table.';
    }
}
