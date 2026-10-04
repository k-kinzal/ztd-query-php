<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * A column name that a table change or a partitioning names but the completely known table does not have.
 *
 * The server rejects the statement: ER_BAD_FIELD_ERROR for a changed,
 * renamed, altered or partitioning column, ER_CANT_DROP_FIELD_OR_KEY for a
 * dropped one. A column the same statement already dropped, changed or
 * renamed away counts as absent.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table.html.
 *
 * @visibility public
 * @example Describing the problem
 *     (new \SqlSemantics\Platform\MySql\Statement\Alter\Problem\UnknownColumn(new \SqlSemantics\Statement\Identifier\Name('c')))->message() // => 'Column c does not exist in the table.'
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
