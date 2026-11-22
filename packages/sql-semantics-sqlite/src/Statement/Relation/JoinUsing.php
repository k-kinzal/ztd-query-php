<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Relation;

use SqlSemantics\Platform\Sqlite\Model\Contract\NameUse;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Node;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * The USING constraint of a join: the columns both sides are joined on and merged by.
 *
 * @visibility SqlSemantics
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
