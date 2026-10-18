<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression;

use LogicException;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Platform\MySql\Rules\Expression\Shape\QueryWidth;
use SqlSemantics\Platform\MySql\Rules\Expression\Shape\RowShapes;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\Quantifier;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Invalid;

/**
 * Stored subquery membership or quantification with a deferred operand constraint.
 *
 * Both the compared operand and the complete query remain structured. The
 * ordinary scalar predicate types reject this contradictory fixed shape.
 *
 * @visibility public
 * @example Refusing to defer a compatible predicate
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredSubqueryPredicate(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2'))])) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class DeferredSubqueryPredicate implements DeferredOperand
{
    use Snapshot;

    /**
     * @param Scalar|RowOperand|DeferredOperand $operand The compared operand
     * @param Query $query The complete subquery
     * @param ComparisonOperator|null $operator Null for IN; otherwise the quantified comparison
     * @param Quantifier|null $quantifier Null exactly for IN
     * @param bool $negated Whether the IN predicate is negated
     */
    public function __construct(public readonly Scalar|RowOperand|DeferredOperand $operand, public readonly Query $query, public readonly ?ComparisonOperator $operator = null, public readonly ?Quantifier $quantifier = null, public readonly bool $negated = false)
    {
        Check::input(($operator === null) === ($quantifier === null), 'A quantified predicate has both an operator and a quantifier.');
        Check::input($operator !== ComparisonOperator::NullSafeEqual, 'Null-safe equality cannot be quantified.');
        Check::input(!$negated || $operator === null, 'Only IN carries a separate negation.');
        Check::input((new Precedence())->fits($operand, $operator === null ? Precedence::PREDICATE : Precedence::BOOL_PRI, $operator === null ? Precedence::BIT_EXPR : Precedence::BOOL_PRI), 'The compared operand preserves its precedence.');
        Check::input($operand instanceof DeferredOperand || $this->shape() !== null || (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadColumns())->problem($query) !== null, 'A deferred predicate has an invocation-time operand constraint.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\SubqueryOptions())->check($query);
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives both dependencies without reporting an invocation-time shape condition.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        (new \SqlSemantics\Platform\MySql\Rules\Expression\SubqueryRows())->checkLimit($this->query, $derivation);
        $operand = $derivation->scalar($this->operand, $environment);
        $derivation->query($this->query, $environment);
        return new ScalarFact(new Invalid($this->problem()), $operand->nullability);
    }

    /**
     * Exposes the compared operand; the query is retained separately as a Query.
     *
     * @return non-empty-list<Scalar|RowOperand|DeferredOperand>
     */
    public function operands(): array
    {
        return [$this->operand];
    }

    /**
     * Answers whether this form permits a row-valued comparison.
     */
    public function rows(): bool
    {
        return $this->operator === null || ($this->operator === ComparisonOperator::Equal && $this->quantifier === Quantifier::Any) || ($this->operator === ComparisonOperator::NotEqual && $this->quantifier === Quantifier::All);
    }

    /**
     * Describes the fixed shape conflict, independently of catalog resolution.
     */
    public function shape(): OperandColumns|\SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse|null
    {
        if ($this->operand instanceof DeferredOperand) {
            return null;
        }
        if (!$this->rows()) {
            if ($this->operand instanceof RowOperand) {
                return new OperandColumns(1, count((new RowShapes())->parts($this->operand) ?? []));
            }
            $width = (new QueryWidth())->of($this->query);
            if ($width !== null && $width > 1) {
                return new OperandColumns(1, $width);
            }
        }
        return (new RowShapes())->query($this->operand, $this->query);
    }

    /**
     * Answers the first fixed condition retained by this predicate.
     *
     * @throws LogicException If the constructor invariant was bypassed
     */
    public function problem(): \SqlSemantics\Statement\Fact\Diagnostic
    {
        return $this->operand instanceof DeferredOperand ? $this->operand->problem() : ((new \SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadColumns())->problem($this->query) ?? $this->shape() ?? throw new LogicException('A deferred subquery predicate has a fixed operand constraint.'));
    }

    /**
     * Writes the complete membership or quantified predicate.
     */
    public function render(Output $out): void
    {
        $out->node($this->operand);
        if ($this->operator !== null && $this->quantifier !== null) {
            $out->symbol($this->operator->value)->keyword($this->quantifier->value);
        } else {
            if ($this->negated) {
                $out->keyword('NOT');
            }
            $out->keyword('IN');
        }
        $out->symbol('(')->node($this->query)->symbol(')');
    }
}
