<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Operator;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Fact\ScalarFact;

/**
 * A stored prefix operator around an operand with an invocation-time condition.
 *
 * The complete operator and operand remain distinct from ordinary Scalar.
 * Grouping and lexical requirements are checked before this value is published.
 *
 * @visibility public
 * @example Keeping the operator outside the ordinary scalar domain
 *     $operand = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredTimeZone(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\Text('+00:00'));
 *     (new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Operator\DeferredUnary(\SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator::Minus, $operand)) instanceof \SqlSemantics\Statement\Scalar // => false
 */
final class DeferredUnary implements DeferredOperand
{
    use \SqlSemantics\Statement\Semantics\ScalarSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * Retains the operator's typed operands and observable spelling.
     */
    public function __construct(public readonly \SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator $operator, public readonly DeferredOperand|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand $operand, public readonly bool $keyword = false, ?ScalarFact $scalarInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($scalarInformation !== null) {
            $this->initializeScalarInformation($scalarInformation);
        }
        Check::input(!$keyword || $operator === \SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator::Not, 'Only tight negation has a keyword spelling.');
        Check::input((new Precedence())->opening($operand) >= Precedence::PREFIX, 'The operand of a prefix operator needs grouping.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Binds the retained operand without reporting its invocation condition.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        Check::input(!$this->keyword || $derivation->context->profile->lexical->highNotPrecedence, 'The tight NOT keyword requires HIGH_NOT_PRECEDENCE.');
        if (in_array($this->operator, [\SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator::Not, \SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator::PairedNot], true) && !$this->keyword) {
            \SqlSemantics\Platform\MySql\Statement\Notice\Deprecation::raise(\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::BangNot, $derivation, $this, false);
        }
        $derivation->scalar($this->operand, $environment);
        return new ScalarFact(new \SqlSemantics\Statement\Type\Invalid($this->problem()), \SqlSemantics\Statement\Type\Nullability::Dependent);
    }

    /**
     * @return non-empty-list<DeferredOperand|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand> The retained preparation dependency
     */
    public function operands(): array
    {
        return [$this->operand];
    }

    /**
     * Preserves the operand's typed invocation condition.
     */
    public function problem(): Diagnostic
    {
        return (new \SqlSemantics\Platform\MySql\Rules\Expression\Preparation\DeferredArguments())->problem([$this->operand]);
    }

    /**
     * Writes the operator and retained operand in their original roles.
     */
    public function render(Output $out): void
    {
        if ($this->keyword) {
            $out->keyword('NOT');
        } elseif ($this->operator === \SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator::PairedNot) {
            $out->symbol('!')->glue()->symbol('!');
        } else {
            $out->symbol($this->operator->value);
        }
        $out->node($this->operand);
    }
}
