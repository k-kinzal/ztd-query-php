<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Platform\MySql\Rules\Expression\Shape\RowShapes;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\IntervalAddition;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Type\Invalid;

/**
 * A stored arithmetic operation containing a row-valued scalar use.
 *
 * The instruction is a valid stored definition even though reaching this
 * computation raises an operand-width condition after binding its dependencies.
 * Scalar-only arithmetic uses Arithmetic; this class never implements Scalar.
 *
 * @visibility public
 * @example Refusing scalar-only deferred arithmetic
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredArithmetic(\SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator::Plus, new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class DeferredArithmetic implements DeferredOperand
{
    use \SqlSemantics\Statement\Semantics\ScalarSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param ArithmeticOperator $operator The arithmetic or bit operator
     * @param Scalar|RowOperand|DeferredOperand $left The first operand to prepare
     * @param Scalar|RowOperand|DeferredOperand $right The second operand to prepare
     */
    public function __construct(public readonly ArithmeticOperator $operator, public readonly Scalar|RowOperand|DeferredOperand $left, public readonly Scalar|RowOperand|DeferredOperand $right, ?ScalarFact $scalarInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($scalarInformation !== null) {
            $this->initializeScalarInformation($scalarInformation);
        }
        Check::input(!$left instanceof Scalar || !$right instanceof Scalar, 'Deferred arithmetic retains a row-valued use.');
        $precedence = new Precedence();
        Check::input($precedence->fits($left, $operator->level(), $operator->level()), 'The left arithmetic operand needs grouping.');
        Check::input($precedence->opening($right) > $operator->level(), 'The right arithmetic operand needs grouping.');
        $leading = $precedence->leading($right);
        Check::input(!($leading instanceof IntervalAddition || ($leading instanceof Operator\DeferredInterval && $leading->leading)) || ($operator !== ArithmeticOperator::Plus && $operator !== ArithmeticOperator::Minus), 'A leading interval needs grouping after this operator.');
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
        if ($this->operator === ArithmeticOperator::IntegerDivide) {
            $out->keyword('DIV');
        } else {
            $out->symbol($this->operator->value);
        }
        $out->node($this->right);
    }
}
