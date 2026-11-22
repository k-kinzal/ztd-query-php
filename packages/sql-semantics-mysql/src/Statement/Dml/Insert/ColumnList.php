<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Insert;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Node;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Name\TableWildcard;

/**
 * The parenthesized column list of INSERT and REPLACE; an empty list writes no column of the rows.
 *
 * The grammars of MySQL 5.6 and 5.7 also accept a qualified star in the
 * list, which the server does not expand and reports as an unknown column.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/insert.html.
 *
 * @visibility SqlSemantics
 */
final class ColumnList implements Node
{
    use Snapshot;

    /**
     * @var list<ColumnUse|TableWildcard> The columns in written order
     */
    public readonly array $columns;

    /**
     * @param list<ColumnUse|TableWildcard> $columns The columns in written order
     * @throws InvalidConstruction When a member is neither a column nor a qualified star
     */
    public function __construct(array $columns)
    {
        $members = [];
        foreach (Check::listOf($columns, Node::class, 'A column list is an ordered list of columns.') as $column) {
            Check::input($column instanceof ColumnUse || $column instanceof TableWildcard, 'A column list holds columns and qualified stars.');
            $members[] = $column;
        }
        $this->columns = $members;
    }

    /**
     * Writes the parenthesized list.
     */
    public function render(Output $out): void
    {
        $out->symbol('(')->list($this->columns)->symbol(')');
    }
}
