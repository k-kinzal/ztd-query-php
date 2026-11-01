<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Relation;

use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;

/**
 * The ON constraint of a join.
 *
 * @visibility public
 * @example Reading the condition of a join
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT 1 FROM t JOIN u ON t.a = u.a');
 *     $query->from->steps[0]->constraint->condition->right->qualifier->name->value // => 'u'
 */
final class JoinOn implements Node
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Scalar $condition The join condition
     */
    public function __construct(public readonly Scalar $condition, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
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
