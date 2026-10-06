<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Mutation;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * An assignment of one value to one column of the written table.
 *
 * @visibility public
 * @example Reading an assignment
 *     $update = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('UPDATE t SET a = 1');
 *     [$update->statement->assignments[0]->column->value, $update->statement->assignments[0]->value->digits] // => ['a', '1']
 */
final class Assignment implements Node
{
    use Snapshot;

    /**
     * @param Name $column The assigned column
     * @param Scalar $value The new value
     */
    public function __construct(public readonly Name $column, public readonly Scalar $value)
    {
    }

    /**
     * Writes the assignment.
     */
    public function render(Output $out): void
    {
        $out->name($this->column, NameUse::Column)->symbol('=')->node($this->value);
    }
}
