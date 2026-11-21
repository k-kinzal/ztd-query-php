<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml;

use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Node;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;

/**
 * One assignment `column = value` of SET, ON DUPLICATE KEY UPDATE, UPDATE or LOAD DATA.
 *
 * The statement that holds the assignment resolves the column among the
 * tables it writes and derives the value; `=` and `:=` are one operator here.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/update.html.
 *
 * @visibility public
 * @example Reading an assignment of UPDATE
 *     $update = (new \SqlSemantics\Platform\MySql\Semantics())->inspect('UPDATE t SET a = a + 1');
 *     $update->statement->assignments[0]->column->name->value // => 'a'
 */
final class Assignment implements Node
{
    use Snapshot;

    /**
     * @param ColumnUse $column The column assigned to
     * @param Scalar $value The value: an expression or DEFAULT
     */
    public function __construct(public readonly ColumnUse $column, public readonly Scalar $value)
    {
    }

    /**
     * Writes the assignment.
     */
    public function render(Output $out): void
    {
        $out->node($this->column)->symbol('=')->node($this->value);
    }
}
