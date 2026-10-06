<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Key;

use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * The CONSTRAINT clause before a key or check: the keyword with an optional constraint symbol.
 *
 * Without a symbol the server generates the constraint name. MySQL 5.x lets
 * a table qualify the symbol; the qualifier is kept as written.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html.
 *
 * @visibility public
 * @example Reading the symbol of a constraint
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (a INT, CONSTRAINT c UNIQUE (a))');
 *     $create->statement->elements[1]->constraint->name->column->value // => 'c'
 */
final class ConstraintName implements Node
{
    use Snapshot;

    /**
     * @param ColumnName|null $name The constraint symbol, when written
     */
    public function __construct(public readonly ?ColumnName $name = null)
    {
    }

    /**
     * Writes CONSTRAINT and the symbol.
     */
    public function render(Output $out): void
    {
        $out->keyword('CONSTRAINT')->node($this->name);
    }
}
