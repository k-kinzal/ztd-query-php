<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Branching;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A stored CASE branch with a condition or result requiring deferred preparation.
 *
 * Both roles are retained even when the condition will never select the result.
 * Ordinary scalar-only branches use CaseBranch.
 *
 * @visibility public
 * @example Retaining an unselected row result
 *     $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')]);
 *     $branch = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Branching\DeferredCaseBranch(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('0'), $row);
 *     $branch->result === $row // => true
 */
final class DeferredCaseBranch implements Node
{
    use Snapshot;

    /**
     * @param Scalar|RowOperand|DeferredOperand $condition The WHEN operand
     * @param Scalar|RowOperand|DeferredOperand $result The THEN operand
     */
    public function __construct(public readonly Scalar|RowOperand|DeferredOperand $condition, public readonly Scalar|RowOperand|DeferredOperand $result)
    {
        Check::input(!$condition instanceof Scalar || !$result instanceof Scalar, 'A deferred CASE branch retains a preparation condition.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Writes both operands in their original branch roles.
     */
    public function render(Output $out): void
    {
        $out->keyword('WHEN')->node($this->condition)->keyword('THEN')->node($this->result);
    }
}
