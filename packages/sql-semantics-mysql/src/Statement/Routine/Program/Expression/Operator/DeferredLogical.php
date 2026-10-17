<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Operator;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Platform\MySql\Rules\Expression\Shape\RowShapes;
use SqlSemantics\Platform\MySql\Statement\Expression\LogicalOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Invalid;

/**
 * A stored logical operation containing a row-valued scalar use.
 *
 * The instruction is a valid stored definition even though reaching this
 * computation raises an operand-width condition after binding its dependencies.
 * Scalar-only logical computation uses Logical; this class never implements Scalar.
 *
 * @visibility public
 * @example Refusing scalar-only deferred logical
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Operator\DeferredLogical(\SqlSemantics\Platform\MySql\Statement\Expression\LogicalOperator::And, new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class DeferredLogical implements DeferredOperand
{
    use Snapshot;

    /**
     * @param LogicalOperator $operator The logical operator
     * @param Scalar|RowOperand|DeferredOperand $left The first operand to prepare
     * @param Scalar|RowOperand|DeferredOperand $right The second operand to prepare
     * @param bool $symbolic Whether AND or OR uses its symbol spelling
     */
    public function __construct(public readonly LogicalOperator $operator, public readonly Scalar|RowOperand|DeferredOperand $left, public readonly Scalar|RowOperand|DeferredOperand $right, public readonly bool $symbolic = false)
    {
        Check::input(!$left instanceof Scalar || !$right instanceof Scalar, 'Deferred logical retains a row-valued use.');
        $precedence = new Precedence();
        Check::input($precedence->fits($left, $operator->level(), $operator->level()), 'The left logical operand needs grouping.');
        Check::input($precedence->opening($right) > $operator->level(), 'The right logical operand needs grouping.');
        Check::input(!$symbolic || $operator !== LogicalOperator::Xor, 'XOR has no symbolic spelling.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives retained dependencies without raising the invocation condition.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        Check::input(!$this->symbolic || $this->operator !== LogicalOperator::Or || !$derivation->context->profile->lexical->pipesAsConcat, 'Symbolic OR requires PIPES_AS_CONCAT to be disabled.');
        $left = $derivation->scalar($this->left, $environment);
        if ($this->symbolic) {
            \SqlSemantics\Platform\MySql\Statement\Notice\Deprecation::raise($this->operator === LogicalOperator::Or ? \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::PipesOr : \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::AmpersandsAnd, $derivation, $this->left);
        }
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
    public function problem(): \SqlSemantics\Statement\Fact\Diagnostic
    {
        $operand = $this->left instanceof Scalar ? $this->right : $this->left;
        return $operand instanceof DeferredOperand ? $operand->problem() : new OperandColumns(1, count((new RowShapes())->parts($operand) ?? []));
    }

    /**
     * Writes the operation without changing the operand roles or grouping.
     */
    public function render(Output $out): void
    {
        $out->node($this->left);
        if ($this->symbolic) {
            $out->symbol($this->operator === LogicalOperator::Or ? '||' : '&&');
        } else {
            $out->keyword($this->operator->value);
        }
        $out->node($this->right);
    }
}
