<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Predicate;

use LogicException;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Platform\MySql\Rules\Expression\Shape\RowShapes;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A predicate retaining a non-scalar use until its owning query is prepared.
 *
 * Every operand remains available for binding, including in an unused CTE or a skipped stored instruction.
 * Ordinary scalar predicates use their Scalar structure instead.
 *
 * @visibility public
 * @example Keeping row uses outside ordinary scalar positions
 *     $value = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Predicate\DeferredRegexp(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('3')]));
 *     $value instanceof \SqlSemantics\Statement\Scalar // => false
 */
final class DeferredRegexp implements DeferredOperand
{
    use Snapshot;

    /**
     * @param Scalar|RowOperand|DeferredOperand $operand The matched expression
     * @param Scalar|RowOperand|DeferredOperand $pattern The pattern
     * @param bool $negated Whether NOT precedes REGEXP
     */
    public function __construct(public readonly Scalar|RowOperand|DeferredOperand $operand, public readonly Scalar|RowOperand|DeferredOperand $pattern, public readonly bool $negated = false)
    {
        $precedence = new Precedence();
        Check::input(!$operand instanceof Scalar || !$pattern instanceof Scalar, 'A deferred REGEXP contains a non-scalar operand.');
        Check::input($precedence->fits($operand, Precedence::PREDICATE, Precedence::BIT_EXPR), 'The REGEXP operand requires grouping.');
        Check::input($precedence->admits($pattern, Precedence::BIT_EXPR), 'The REGEXP pattern requires grouping.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Binds all retained dependencies without raising the invocation constraint.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $nullability = Nullability::NotNull;
        foreach ($this->operands() as $operand) {
            $nullability = $nullability->propagate($derivation->scalar($operand, $environment)->nullability);
        }
        return new ScalarFact(new Invalid($this->problem()), $nullability);
    }

    /**
     * @return non-empty-list<Scalar|RowOperand|DeferredOperand> Dependencies in written order
     */
    public function operands(): array
    {
        return [$this->operand, $this->pattern];
    }

    /**
     * Answers the first scalar-use constraint.
     *
     * @throws LogicException If the constructor invariant has been bypassed
     */
    public function problem(): \SqlSemantics\Statement\Fact\Diagnostic
    {
        foreach ($this->operands() as $operand) {
            if (!$operand instanceof Scalar) {
                return $operand instanceof DeferredOperand ? $operand->problem() : new OperandColumns(1, count((new RowShapes())->parts($operand) ?? []));
            }
        }
        throw new LogicException('A deferred predicate requires a non-scalar operand.');
    }

    /**
     * Writes every retained operand and optional keyword.
     */
    public function render(Output $out): void
    {
        $out->node($this->operand);
        if ($this->negated) {
            $out->keyword('NOT');
        }
        $out->keyword('REGEXP')->node($this->pattern);
    }
}
