<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Constructor;

use LogicException;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A row containing an expression whose scalar use is deferred until preparation.
 *
 * Unlike an ordinary Row, it cannot participate in a scalar or row comparison
 * until its enclosing query or stored instruction is reached.
 *
 * @visibility public
 * @example Retaining a deferred row element
 *     $value = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Predicate\DeferredRegexp(new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')]), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('3'));
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Constructor\DeferredRow([$value, new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('4')]) instanceof \SqlSemantics\Statement\Scalar // => false
 */
final class DeferredRow implements DeferredOperand
{
    use \SqlSemantics\Statement\Semantics\ScalarSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var non-empty-list<Scalar|RowOperand|DeferredOperand> The elements in order; at least two
     */
    public readonly array $elements;

    /**
     * @param list<Scalar|RowOperand|DeferredOperand> $elements The elements in order; at least two
     * @param OptionalWords $keyword Whether the optional ROW keyword is written
     */
    public function __construct(array $elements, public readonly OptionalWords $keyword = OptionalWords::Omitted, ?ScalarFact $scalarInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($scalarInformation !== null) {
            $this->initializeScalarInformation($scalarInformation);
        }
        $checked = Check::listOf($elements, \SqlSemantics\Statement\Operand::class, 'A row constructor has at least two elements.', 2);
        $accepted = [];
        foreach ($checked as $element) {
            Check::input($element instanceof Scalar || $element instanceof RowOperand || $element instanceof DeferredOperand, 'A deferred row element retains a scalar, row or deferred role.');
            $accepted[] = $element;
        }
        Check::input(array_filter($accepted, static fn ($element): bool => $element instanceof DeferredOperand) !== [], 'A deferred row retains at least one deferred element.');
        $this->elements = $accepted;
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives every element and the row.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $nullability = Nullability::NotNull;
        foreach ($this->elements as $element) {
            $nullability = $nullability->propagate($derivation->scalar($element, $environment)->nullability);
        }

        return new ScalarFact(new \SqlSemantics\Statement\Type\Invalid($this->problem()), $nullability);
    }

    /**
     * @return non-empty-list<Scalar|RowOperand|DeferredOperand> Elements in written order
     */
    public function operands(): array
    {
        return $this->elements;
    }

    /**
     * Describes the first deferred element; ordinary nested rows remain legal.
     *
     * @throws LogicException If the constructor invariant has been bypassed
     */
    public function problem(): \SqlSemantics\Statement\Fact\Diagnostic
    {
        foreach ($this->elements as $element) {
            if ($element instanceof DeferredOperand) {
                return $element->problem();
            }
        }
        throw new LogicException('A deferred row retains at least one deferred element.');
    }

    /**
     * Writes the ROW keyword when it is written and the elements in parentheses.
     */
    public function render(Output $out): void
    {
        if ($this->keyword === OptionalWords::Written) {
            $out->keyword('ROW')->glue();
        }
        $out->symbol('(')->list($this->elements)->symbol(')');
    }
}
