<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Snapshot;

/**
 * Grouping around a stored computation, preserving its deferred operand role.
 *
 * Parentheses and ODBC braces retain the same operand and preparation condition.
 *
 * @visibility public
 * @example Keeping stored arithmetic outside Scalar
 *     $value = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredArithmetic(\SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator::Plus, new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('3')]));
 *     (new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredGrouped($value)) instanceof \SqlSemantics\Statement\Scalar // => false
 */
final class DeferredGrouped implements DeferredOperand
{
    use Snapshot;

    /**
     * @param DeferredOperand $operand The grouped stored computation
     * @param \SqlSemantics\Statement\Identifier\Name|null $escape An ODBC escape kind, or null for ordinary parentheses
     */
    public function __construct(public readonly DeferredOperand $operand, public readonly ?\SqlSemantics\Statement\Identifier\Name $escape = null)
    {
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Keeps the computation's binding and type facts.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        return $derivation->scalar($this->operand, $environment);
    }

    /**
     * Exposes the dependencies in their preparation order.
     *
     * @return non-empty-list<\SqlSemantics\Statement\Scalar|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|DeferredOperand>
     */
    public function operands(): array
    {
        return [$this->operand];
    }

    /**
     * Keeps the enclosed computation's operand constraint.
     */
    public function problem(): \SqlSemantics\Statement\Fact\Diagnostic
    {
        return $this->operand->problem();
    }

    /**
     * Writes the enclosing parentheses.
     */
    public function render(Output $out): void
    {
        if ($this->escape !== null) {
            $out->symbol('{')->name($this->escape, \SqlSemantics\Contract\NameUse::Label)->node($this->operand)->symbol('}');
            return;
        }
        $out->symbol('(')->node($this->operand)->symbol(')');
    }
}
