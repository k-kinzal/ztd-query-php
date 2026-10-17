<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Operator;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Platform\MySql\Rules\Expression\Preparation\DeferredArguments;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Invalid;

/**
 * Stored interval arithmetic retaining a non-scalar date or interval quantity.
 *
 * Both operands and the unit survive definition-time binding. Invoking the
 * computation prepares its dependencies before reporting the scalar constraint.
 *
 * @visibility public
 * @example Refusing ordinary scalar interval arithmetic
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Operator\DeferredInterval(new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral(), new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral(), \SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit::Day) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing ambiguous interval quantity spelling
 *     $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')]);
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Operator\DeferredInterval(new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral(), $row, \SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit::Day) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class DeferredInterval implements DeferredOperand
{
    use Snapshot;

    /**
     * @param Scalar|RowOperand|DeferredOperand $operand The date operand
     * @param Scalar|RowOperand|DeferredOperand $quantity The interval quantity
     * @param IntervalUnit $unit The interval unit
     * @param bool $subtract Whether to subtract the trailing interval
     * @param bool $leading Whether the interval is written before the date
     */
    public function __construct(public readonly Scalar|RowOperand|DeferredOperand $operand, public readonly Scalar|RowOperand|DeferredOperand $quantity, public readonly IntervalUnit $unit, public readonly bool $subtract = false, public readonly bool $leading = false)
    {
        Check::input(!$operand instanceof Scalar || !$quantity instanceof Scalar, 'A deferred interval retains a non-scalar operand.');
        Check::input(!$leading || !$subtract, 'A leading interval permits addition only.');
        Check::input(!$quantity instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Row || $quantity->keyword === \SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written, 'An interval row quantity needs grouping or the ROW keyword to distinguish it from the INTERVAL function.');
        $precedence = new Precedence();
        Check::input($leading ? $precedence->opening($operand) >= Precedence::NEGATION : $precedence->fits($operand, Precedence::ADDITIVE, Precedence::ADDITIVE), 'The date operand needs grouping.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives both dependencies without invoking the retained condition.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $operand = $derivation->scalar($this->operand, $environment);
        $quantity = $derivation->scalar($this->quantity, $environment);
        return new ScalarFact(new Invalid($this->problem()), $operand->nullability->propagate($quantity->nullability));
    }

    /**
     * @return non-empty-list<Scalar|RowOperand|DeferredOperand> Date and quantity in preparation order
     */
    public function operands(): array
    {
        return [$this->operand, $this->quantity];
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
        if (!$this->leading) {
            $out->node($this->operand)->symbol($this->subtract ? '-' : '+');
        }
        $out->keyword('INTERVAL')->node($this->quantity)->keyword($this->unit->value);
        if ($this->leading) {
            $out->symbol('+')->node($this->operand);
        }
    }
}
