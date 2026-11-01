<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\Preparation\DeferredArguments;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Type\Invalid;

/**
 * A DATE_ADD or DATE_SUB call retaining both operands and their deferred condition.
 *
 * The complete call remains available when its stored instruction is reached.
 *
 * @visibility public
 * @example Refusing to defer an ordinary scalar call
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredDateArithmetic(false, new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral(), new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral(), \SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit::Day) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class DeferredDateArithmetic implements DeferredOperand
{
    use \SqlSemantics\Statement\Semantics\ScalarSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param bool $subtract Whether the function subtracts the interval
     * @param Scalar|RowOperand|DeferredOperand $date The date operand
     * @param Scalar|RowOperand|DeferredOperand $quantity The interval quantity
     * @param IntervalUnit $unit The interval unit
     * @param \SqlSemantics\Platform\MySql\Statement\Call\Keyword\DateArithmeticAlias|null $alias A synonym matching the subtraction choice
     */
    public function __construct(public readonly bool $subtract, public readonly Scalar|RowOperand|DeferredOperand $date, public readonly Scalar|RowOperand|DeferredOperand $quantity, public readonly IntervalUnit $unit, ?ScalarFact $scalarInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, public readonly ?\SqlSemantics\Platform\MySql\Statement\Call\Keyword\DateArithmeticAlias $alias = null)
    {
        if ($scalarInformation !== null) {
            $this->initializeScalarInformation($scalarInformation);
        }
        Check::input(!$date instanceof Scalar || !$quantity instanceof Scalar, 'A deferred date call retains a non-scalar operand.');
        Check::input($alias === null || $alias->subtracts() === $subtract, 'A date-arithmetic alias names the retained operation.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives both dependencies without invoking the retained condition.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $operand = $derivation->scalar($this->date, $environment);
        $quantity = $derivation->scalar($this->quantity, $environment);
        return new ScalarFact(new Invalid($this->problem()), $operand->nullability->propagate($quantity->nullability));
    }

    /**
     * @return non-empty-list<Scalar|RowOperand|DeferredOperand> Date and quantity in preparation order
     */
    public function operands(): array
    {
        return [$this->date, $this->quantity];
    }

    /**
     * Answers the first retained operand condition.
     */
    public function problem(): Diagnostic
    {
        return (new DeferredArguments())->problem($this->operands());
    }

    /**
     * Writes the retained operation with its original operand roles.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->alias->value ?? ($this->subtract ? 'DATE_SUB' : 'DATE_ADD'))->glue()->symbol('(')->node($this->date)->symbol(',')->keyword('INTERVAL')->node($this->quantity)->keyword($this->unit->value)->symbol(')');
    }
}
