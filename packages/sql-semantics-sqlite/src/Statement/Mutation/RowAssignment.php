<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Mutation;

use SqlSemantics\Platform\Sqlite\Model\Contract\NameUse;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Node;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * An assignment of a row value to a parenthesised list of columns of the written table.
 *
 * Source: https://sqlite.org/rowvalue.html#update_table_set_c1_c2_value_value.
 *
 * @visibility SqlSemantics
 */
final class RowAssignment implements Node
{
    use Snapshot;

    /**
     * @var non-empty-list<Name> The assigned columns in written order
     */
    public readonly array $columns;

    /**
     * @param list<Name> $columns The assigned columns in written order; at least one
     * @param Scalar $value The row of new values
     */
    public function __construct(array $columns, public readonly Scalar $value)
    {
        $this->columns = Check::listOf($columns, Name::class, 'A row assignment names at least one column.', 1);
    }

    /**
     * Writes the assignment.
     */
    public function render(Output $out): void
    {
        $out->symbol('(');
        foreach ($this->columns as $position => $column) {
            if ($position > 0) {
                $out->symbol(',');
            }
            $out->name($column, NameUse::Column);
        }
        $out->symbol(')')->symbol('=')->node($this->value);
    }
}
