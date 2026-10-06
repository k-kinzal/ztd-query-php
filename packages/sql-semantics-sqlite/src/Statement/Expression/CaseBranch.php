<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression;

use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * One WHEN branch of a CASE expression.
 *
 * @visibility public
 * @example Reading a branch
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze("SELECT CASE WHEN a THEN 'x' END FROM t");
 *     $query->statement->columns[0]->expression->branches[0]->then->value // => 'x'
 */
final class CaseBranch implements Node
{
    use Snapshot;

    /**
     * @param Scalar $when The condition, or the value compared with the base expression
     * @param Scalar $then The result of the branch
     */
    public function __construct(public readonly Scalar $when, public readonly Scalar $then)
    {
    }

    /**
     * Writes the branch.
     */
    public function render(Output $out): void
    {
        $out->keyword('WHEN')->node($this->when)->keyword('THEN')->node($this->then);
    }
}
