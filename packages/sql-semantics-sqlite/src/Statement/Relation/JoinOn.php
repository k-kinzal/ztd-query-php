<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Relation;

use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Statement\Node;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * The ON constraint of a join.
 *
 * @visibility public
 * @example Reading the condition of a join
 *     $query = (new \SqlSemantics\Platform\Sqlite\Semantics())->inspect('SELECT 1 FROM t JOIN u ON t.a = u.a');
 *     $query->statement->from->steps[0]->constraint->condition->right->qualifier->name->value // => 'u'
 */
final class JoinOn implements Node
{
    use Snapshot;

    /**
     * @param Scalar $condition The join condition
     */
    public function __construct(public readonly Scalar $condition)
    {
    }

    /**
     * Writes the constraint.
     */
    public function render(Output $out): void
    {
        $out->keyword('ON')->node($this->condition);
    }
}
