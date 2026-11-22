<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Problem;

use SqlSemantics\Platform\MySql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A column name the completely known table would have twice after a table change.
 *
 * The server rejects the statement with ER_DUP_FIELDNAME. Column names are
 * compared without regard to case.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table.html.
 *
 * @visibility SqlSemantics
 * @example Describing the problem
 *     (new \SqlSemantics\Platform\MySql\Statement\Alter\Problem\DuplicateColumn(new \SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name('a')))->message() // => 'Column a would be defined more than once.'
 */
final class DuplicateColumn implements Diagnostic
{
    use Snapshot;

    /**
     * @param Name $column The repeated name as the change writes it
     */
    public function __construct(public readonly Name $column)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return 'Column ' . $this->column->value . ' would be defined more than once.';
    }
}
