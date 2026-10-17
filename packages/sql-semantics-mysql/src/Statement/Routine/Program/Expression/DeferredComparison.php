<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Invalid;

/**
 * A comparison containing a computation whose scalar use is deferred until invocation.
 *
 * The instruction is a valid stored definition even though reaching this
 * computation raises an operand-width condition after binding its dependencies.
 * Ordinary scalar and compatible-row comparisons use Comparison.
 *
 * @visibility public
 * @example Refusing scalar-only deferred comparison
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredComparison(\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal, new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class DeferredComparison implements DeferredOperand
{
    use Snapshot;

    /**
     * @param ComparisonOperator $operator The comparison operator
     * @param Scalar|RowOperand|DeferredOperand $left The first operand to prepare
     * @param Scalar|RowOperand|DeferredOperand $right The second operand to prepare
     */
    public function __construct(public readonly ComparisonOperator $operator, public readonly Scalar|RowOperand|DeferredOperand $left, public readonly Scalar|RowOperand|DeferredOperand $right)
    {
        Check::input($left instanceof DeferredOperand || $right instanceof DeferredOperand, 'A deferred comparison retains an invocation-time computation.');
        $precedence = new Precedence();
        Check::input($precedence->fits($left, Precedence::BOOL_PRI, Precedence::BOOL_PRI), 'The left comparison operand needs grouping.');
        Check::input($precedence->opening($right) > Precedence::BOOL_PRI, 'The right comparison operand needs grouping.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives retained dependencies without raising the invocation condition.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $left = $derivation->scalar($this->left, $environment);
        $right = $derivation->scalar($this->right, $environment);
        return new ScalarFact(new Invalid($this->problem()), $left->nullability->propagate($right->nullability));
    }

    /**
     * Exposes the dependencies in their preparation order.
     *
     * @return non-empty-list<Scalar|RowOperand|DeferredOperand>
     */
    public function operands(): array
    {
        return [$this->left, $this->right];
    }

    /**
     * Answers the first operand-width constraint, following operand order.
     */
    public function problem(): OperandColumns
    {
        if ($this->left instanceof DeferredOperand) {
            return $this->left->problem();
        }
        Check::invariant($this->right instanceof DeferredOperand, 'A deferred comparison contains an invocation-time operand.');
        return $this->right->problem();
    }

    /**
     * Writes the operation without changing the operand roles or grouping.
     */
    public function render(Output $out): void
    {
        $out->node($this->left)->symbol($this->operator->value)->node($this->right);
    }
}
