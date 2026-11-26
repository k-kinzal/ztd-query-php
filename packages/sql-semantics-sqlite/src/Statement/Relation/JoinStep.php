<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Relation;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Statement\Node;
use SqlSemantics\Platform\Sqlite\Model\Statement\Relation;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * One further term of a join chain: the operator, the joined relation and its constraint.
 *
 * @visibility SqlSemantics
 */
final class JoinStep implements Node
{
    use Snapshot;

    /**
     * @param JoinOperator $operator The operator before the term
     * @param Relation $relation The joined relation
     * @param JoinOn|JoinUsing|null $constraint The constraint written after the term
     */
    public function __construct(public readonly JoinOperator $operator, public readonly Relation $relation, public readonly JoinOn|JoinUsing|null $constraint = null)
    {
        Check::input(!$relation instanceof JoinChain, 'A join chain used as a term is written in parentheses.');
    }

    /**
     * Writes the operator, the term and the constraint.
     */
    public function render(Output $out): void
    {
        $out->node($this->operator)->node($this->relation)->node($this->constraint);
    }
}
