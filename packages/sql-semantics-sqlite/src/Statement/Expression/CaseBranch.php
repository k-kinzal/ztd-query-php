<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression;

use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Statement\Node;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * One WHEN branch of a CASE expression.
 *
 * @visibility SqlSemantics
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
