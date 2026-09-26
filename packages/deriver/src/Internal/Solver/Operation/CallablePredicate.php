<?php

declare(strict_types=1);

namespace Deriver\Internal\Solver\Operation;

use Deriver\Internal\IR\Instruction;
use Deriver\Internal\Solver\Call\CallableCheck;
use Deriver\Internal\Solver\Call\PassedArgument;
use Deriver\Internal\Solver\Call\UnknownCall;
use Deriver\Internal\Solver\Context;
use Deriver\Internal\Solver\State;
use Deriver\Value\Term;

/**
 * Evaluates the supported callable predicate and retains unmodeled overload effects.
 * @visibility root
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
     * @param State $state Bound model state
     * @param list<Term> $values Value, syntax mode, and optional reference output
     * @return list<State> Boolean predicate or explicit overload residuals
     */
    public function apply(Instruction $instruction, State $state, array $values): array
    {
        if (($values[1]->kind ?? 'constant') !== 'constant' || ($values[1]->literal ?? false) !== false || ($values[2]->kind ?? 'omitted') !== 'omitted') {
            $location = $state->locals['callable_name'] ?? null;
            return (new UnknownCall($this->context))->apply($state, $instruction, [new PassedArgument($values[0] ?? Term::opaque('UNKNOWN_ARGUMENT'), location: $location)], null, 'UNSUPPORTED_MODEL_CASE');
        }
        $predicate = (new CallableCheck($this->context))->evaluate($values[0] ?? Term::constant(null));
        if ($predicate->kind !== 'constant') {
            return (new UnknownCall($this->context))->apply($state, $instruction, [new PassedArgument($values[0] ?? Term::opaque('UNKNOWN_ARGUMENT'))], null, 'UNSUPPORTED_MODEL_CASE');
        }
        $state->registers[$instruction->result] = $predicate;
        return [$state];
    }
}
