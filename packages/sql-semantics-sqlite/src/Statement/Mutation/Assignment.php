<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Mutation;

use SqlSemantics\Platform\Sqlite\Model\Contract\NameUse;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Node;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * An assignment of one value to one column of the written table.
 *
 * @visibility public
 * @example Reading an assignment
 *     $update = (new \SqlSemantics\Platform\Sqlite\Semantics())->analyze('UPDATE t SET a = 1');
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
