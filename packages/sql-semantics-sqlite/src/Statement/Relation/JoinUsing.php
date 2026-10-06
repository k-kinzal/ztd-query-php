<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Relation;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * The USING constraint of a join: the columns both sides are joined on and merged by.
 *
 * @visibility public
 * @example Reading the columns of a USING constraint
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT 1 FROM t JOIN u USING (a, b)');
 *     $query->statement->from->steps[0]->constraint->columns[1]->value // => 'b'
 */
final class JoinUsing implements Node
{
    use Snapshot;

    /**
     * @var non-empty-list<Name> The column names in written order
     */
    public readonly array $columns;

    /**
     * @param list<Name> $columns The column names in written order; at least one
     */
    public function __construct(array $columns)
    {
        $this->columns = Check::listOf($columns, Name::class, 'USING names at least one column.', 1);
    }

    /**
     * Writes the constraint.
     */
    public function render(Output $out): void
    {
        $out->keyword('USING')->symbol('(');
        foreach ($this->columns as $position => $column) {
            if ($position > 0) {
                $out->symbol(',');
            }
            $out->name($column, NameUse::Column);
        }
        $out->symbol(')');
    }
}
