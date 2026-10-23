<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression;

use LogicException;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Platform\MySql\Rules\Expression\Shape\RowShapes;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Invalid;

/**
 * Stored list membership whose row-shape constraint is checked on invocation.
 *
 * The program definition preserves all operands even when one list member has
 * a different shape. Ordinary compatible membership uses InList. This value
 * belongs only to a program instruction and never implements Scalar.
 *
 * @visibility public
 * @example Refusing to defer compatible membership
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredMembership(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), [new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class DeferredMembership implements DeferredOperand
{
    use Snapshot;

    /**
     * @var non-empty-list<Scalar|RowOperand|DeferredOperand> The compared members in written order
     */
    public readonly array $elements;

    /**
     * @param Scalar|RowOperand|DeferredOperand $operand The membership operand
     * @param list<Scalar|RowOperand|DeferredOperand> $elements Members including a fixed shape mismatch
     * @param bool $negated Whether NOT precedes IN
     */
    public function __construct(public readonly Scalar|RowOperand|DeferredOperand $operand, array $elements, public readonly bool $negated = false)
    {
        Check::input((new Precedence())->fits($operand, Precedence::PREDICATE, Precedence::BIT_EXPR), 'The membership operand needs grouping.');
        $checked = Check::listOf($elements, \SqlSemantics\Statement\Operand::class, 'Stored membership has at least one member.', 1);
        $accepted = [];
        $mismatch = $operand instanceof DeferredOperand;
        foreach ($checked as $element) {
            Check::input($element instanceof Scalar || $element instanceof RowOperand || $element instanceof DeferredOperand, 'A membership element is a scalar or row.');
            $mismatch = $mismatch || $element instanceof DeferredOperand || (!$operand instanceof DeferredOperand && (new RowShapes())->problem($operand, $element) !== null);
            $accepted[] = $element;
        }
        Check::input($mismatch, 'Deferred membership contains a fixed row-shape constraint.');
        $this->elements = $accepted;
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the dependencies without reporting the invocation-time constraint.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $nullability = $derivation->scalar($this->operand, $environment)->nullability;
        foreach ($this->elements as $element) {
            $nullability = $nullability->propagate($derivation->scalar($element, $environment)->nullability);
        }
        return new ScalarFact(new Invalid($this->problem()), $nullability);
    }

    /**
     * Answers the membership operand followed by its list members.
     *
     * @return non-empty-list<Scalar|RowOperand|DeferredOperand>
     */
    public function operands(): array
    {
        return [$this->operand, ...$this->elements];
    }

    /**
     * Answers the first incompatible row shape.
     *
     * @throws LogicException If the constructor invariant was bypassed
     */
    public function problem(): \SqlSemantics\Statement\Fact\Diagnostic
    {
        if ($this->operand instanceof DeferredOperand) {
            return $this->operand->problem();
        }
        foreach ($this->elements as $element) {
            if ($element instanceof DeferredOperand) {
                return $element->problem();
            }
            $problem = (new RowShapes())->problem($this->operand, $element);
            if ($problem !== null) {
                return $problem;
            }
        }
        throw new LogicException('Deferred membership requires incompatible operand shapes.');
    }

    /**
     * Writes the operand, optional NOT and the complete membership list.
     */
    public function render(Output $out): void
    {
        $out->node($this->operand);
        if ($this->negated) {
            $out->keyword('NOT');
        }
        $out->keyword('IN')->symbol('(')->list($this->elements)->symbol(')');
    }
}
