<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call;

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
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A POSITION call retaining a non-scalar use until its instruction is reached.
 *
 * Every operand remains available for binding and invocation-time preparation.
 * Ordinary scalar calls use their Scalar structure instead.
 *
 * @visibility public
 * @example Keeping row uses outside ordinary scalar positions
 *     $value = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredPosition(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('3')]));
 *     $value instanceof \SqlSemantics\Statement\Scalar // => false
 */
final class DeferredPosition implements DeferredOperand
{
    use \SqlSemantics\Statement\Semantics\ScalarSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Scalar|RowOperand|DeferredOperand $substring The substring to find
     * @param Scalar|RowOperand|DeferredOperand $string The string to search
     */
    public function __construct(public readonly Scalar|RowOperand|DeferredOperand $substring, public readonly Scalar|RowOperand|DeferredOperand $string, ?ScalarFact $scalarInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($scalarInformation !== null) {
            $this->initializeScalarInformation($scalarInformation);
        }
        $precedence = new Precedence();
        Check::input(!$substring instanceof Scalar || !$string instanceof Scalar, 'A deferred POSITION contains a non-scalar operand.');
        Check::input($precedence->admits($substring, Precedence::BIT_EXPR) && !$precedence->absorbs($substring, Precedence::PREDICATE, Precedence::BIT_EXPR), 'The substring of POSITION requires grouping.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Binds all retained dependencies without raising the invocation constraint.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $nullability = Nullability::NotNull;
        foreach ($this->operands() as $substring) {
            $nullability = $nullability->propagate($derivation->scalar($substring, $environment)->nullability);
        }
        return new ScalarFact(new Invalid($this->problem()), $nullability);
    }

    /**
     * @return non-empty-list<Scalar|RowOperand|DeferredOperand> Dependencies in written order
     */
    public function operands(): array
    {
        return [$this->substring, $this->string];
    }

    /**
     * Answers the first scalar-use constraint.
     *
     * @throws LogicException If the constructor invariant has been bypassed
     */
    public function problem(): \SqlSemantics\Statement\Fact\Diagnostic
    {
        foreach ($this->operands() as $substring) {
            if (!$substring instanceof Scalar) {
                return $substring instanceof DeferredOperand ? $substring->problem() : new OperandColumns(1, count((new RowShapes())->parts($substring) ?? []));
            }
        }
        throw new LogicException('A deferred predicate requires a non-scalar operand.');
    }

    /**
     * Writes both operands in their POSITION roles.
     */
    public function render(Output $out): void
    {
        $out->keyword('POSITION')->glue()->symbol('(')->node($this->substring)->keyword('IN')->node($this->string)->symbol(')');
    }
}
