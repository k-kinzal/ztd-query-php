<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Relation;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Snapshot;

/**
 * One further term of a join chain: the operator, the joined relation and its constraint.
 *
 * @visibility public
 * @example Reading a joined term
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT 1 FROM t CROSS JOIN u');
 *     $query->statement->from->steps[0]->relation->name->name->value // => 'u'
 * @example Refusing a chain as a term, which must be written in parentheses
 *     $chain = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT 1 FROM t JOIN u')->statement->from;
 *     new \SqlSemantics\Platform\Sqlite\Statement\Relation\JoinStep(new \SqlSemantics\Platform\Sqlite\Statement\Relation\JoinOperator(), $chain) // throws \SqlSemantics\Diagnostic\InvalidConstruction
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
