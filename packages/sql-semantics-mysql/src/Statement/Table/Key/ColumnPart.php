<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Key;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Query\Direction;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * A key part that names a column, with an optional prefix length and sort direction.
 *
 * Whether the column exists is checked against the table being defined or
 * indexed (MYSQL-TABLE-PROBLEMS-001). The direction is kept as written; an
 * index is ascending without one. Source: https://dev.mysql.com/doc/refman/8.4/en/create-index.html#create-index-column-prefixes,
 * https://dev.mysql.com/doc/refman/8.4/en/descending-indexes.html.
 *
 * @visibility public
 * @example Reading a prefix key part
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (a TEXT, KEY (a(10) DESC))');
 *     [$create->statement->elements[1]->parts[0]->column->value, $create->statement->elements[1]->parts[0]->length->text] // => ['a', '10']
 */
final class ColumnPart implements KeyPart
{
    use Snapshot;

    /**
     * @param Name $column The column
     * @param Numeral|null $length The prefix length, when written
     * @param Direction|null $direction The sort direction, when written
     */
    public function __construct(public readonly Name $column, public readonly ?Numeral $length = null, public readonly ?Direction $direction = null)
    {
    }

    /**
     * Derives nothing: a column name is not an expression; the table checks it.
     */
    public function deriveKeyPart(Derivation $derivation, Environment $scope): void
    {
    }

    /**
     * Writes the column, the length and the direction.
     */
    public function render(Output $out): void
    {
        $out->name($this->column, NameUse::Column);
        if ($this->length !== null) {
            $out->glue()->symbol('(')->node($this->length)->symbol(')');
        }
        if ($this->direction !== null) {
            $out->keyword($this->direction->value);
        }
    }
}
