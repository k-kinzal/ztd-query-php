<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Operation;

use Deriver\Deriver\Evaluator\Execution\Call\CallableCheck;
use Deriver\Deriver\Evaluator\Execution\Call\PassedArgument;
use Deriver\Deriver\Evaluator\Execution\Call\UnknownCall;
use Deriver\Deriver\Evaluator\Execution\Context;
use Deriver\Deriver\Evaluator\Execution\State;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Graph\Instruction;

/**
 * Evaluates the supported callable predicate and retains unhandled overload effects.
 * @visibility Deriver\Deriver\Evaluator
 * @visibility Deriver\Deriver\Evaluator\Execution
 */
final class CallablePredicate
{
    /**
     * @param Context $context Captured declaration world
     */
    public function __construct(public readonly Context $context)
    {
    }

    /**
     * Preserves the reference output of unsupported is_callable overloads.
     * @param Instruction $instruction Predicate destination
     * @param State $state Bound evaluator state
     * @param list<Term> $values Value, syntax mode, and optional reference output
     * @return list<State> Boolean predicate or explicit overload residuals
     */
    public function apply(Instruction $instruction, State $state, array $values): array
    {
        if (($values[1]->kind ?? \Deriver\Deriver\Evaluator\TermKind::Constant) !== \Deriver\Deriver\Evaluator\TermKind::Constant || ($values[1]->literal ?? false) !== false || ($values[2]->kind ?? \Deriver\Deriver\Evaluator\TermKind::Omitted) !== \Deriver\Deriver\Evaluator\TermKind::Omitted) {
            $location = $state->locals['callable_name'] ?? null;
            return (new UnknownCall($this->context))->apply($state, $instruction, [new PassedArgument($values[0] ?? Term::opaque('UNKNOWN_ARGUMENT'), location: $location)], null, 'UNSUPPORTED_EVALUATOR_CASE');
        }
        $predicate = (new CallableCheck($this->context))->evaluate($values[0] ?? Term::constant(null));
        if ($predicate->kind !== \Deriver\Deriver\Evaluator\TermKind::Constant) {
            return (new UnknownCall($this->context))->apply($state, $instruction, [new PassedArgument($values[0] ?? Term::opaque('UNKNOWN_ARGUMENT'))], null, 'UNSUPPORTED_EVALUATOR_CASE');
        }
        $state->registers[$instruction->result] = $predicate;
        return [$state];
    }
}
