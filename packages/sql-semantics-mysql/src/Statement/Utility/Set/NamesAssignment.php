<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Set;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedNamesAssignment;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Source\Span;

/**
 * The keyword form NAMES = expression, including its complete refused operand.
 *
 * This form differs from a quoted variable assignment and from SET NAMES charset.
 * It always has a definition condition. A visible local named names changes that
 * condition; the expression is retained and is never executed. MySQL 5.6 reports
 * the condition at the completed item; later releases finish reading the SET list.
 *
 * @visibility public
 * @example Retaining the expression of a refused keyword assignment
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SET NAMES = 1 + 2');
 *     $statement->toString() // => 'SET NAMES = 1 + 2'
 */
final class NamesAssignment implements SetItem
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Scalar|RowOperand|DeferredOperand $value The complete expression following the assignment operator
     * @param Span|null $operatorSource The optional location of the written assignment operator
     * @param Span|null $inputSource The optional location of the complete SET item
     * @param \SqlSemantics\Platform\MySql\Statement\AssignmentOperator $operator The written assignment sign
     */
    public function __construct(public readonly Scalar|RowOperand|DeferredOperand $value, public readonly ?Span $operatorSource = null, public readonly ?Span $inputSource = null, public readonly \SqlSemantics\Platform\MySql\Statement\AssignmentOperator $operator = \SqlSemantics\Platform\MySql\Statement\AssignmentOperator::Equal)
    {
    }

    /**
     * Derives the retained expression and the ordinary assignment condition.
     * Stored targets are checked by the containing instruction in its lexical scope.
     * Later releases refuse the keyword before looking up variables in its value.
     */
    public function deriveItem(Derivation $derivation): void
    {
        $legacy = $derivation->context->profile->grammar === GrammarRelease::MySql5651;
        if (!$legacy && !$derivation->inProgram()) {
            $this->condition($derivation, false);
        }
        $derive = fn () => $derivation->scalar($this->value, $derivation->environment());
        if ($legacy) {
            $derive();
        } else {
            $derivation->deferred($this->value, $derive);
        }
        if ($legacy && !$derivation->inProgram()) {
            $this->condition($derivation, false);
        }
    }

    /**
     * Publishes the condition of the completed expression without evaluating it.
     */
    public function condition(Derivation $derivation, bool $shadowed): void
    {
        $operator = $this->operatorSource->offset ?? 0;
        $value = $derivation->sources->of($this->value);
        $end = $value === null ? $operator : max($operator, $value->offset + $value->length);
        $problem = new RefusedNamesAssignment($operator, $end, $shadowed);
        $legacy = $derivation->context->profile->grammar === GrammarRelease::MySql5651;
        $phase = $legacy ? NoticePhase::Syntax : NoticePhase::Contextualization;
        $derivation->report($problem);
        $derivation->warn(new ParseFailure($problem, true, $phase), $this, $legacy);
    }

    /**
     * Writes the keyword assignment and its complete expression.
     */
    public function render(Output $out): void
    {
        $out->keyword('NAMES')->symbol($this->operator->value)->node($this->value);
    }
}
