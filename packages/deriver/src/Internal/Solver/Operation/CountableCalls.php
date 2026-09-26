<?php

declare(strict_types=1);

namespace Deriver\Internal\Solver\Operation;

use Deriver\Internal\IR\CallableIR;
use Deriver\Internal\IR\Instruction;
use Deriver\Internal\Solver\Call\CallExecutor;
use Deriver\Internal\Solver\Call\Dispatch;
use Deriver\Internal\Solver\Call\UnknownCall;
use Deriver\Internal\Solver\Completion;
use Deriver\Internal\Solver\Machine;
use Deriver\Internal\Solver\State;
use Deriver\Value\Term;

/**
 * Executes Countable::count using the captured receiver and ordinary source effects.
 * @visibility root
 */
final class CountableCalls
{
    /**
     * @param Machine $machine Shared source evaluator
     */
    public function __construct(public readonly Machine $machine)
    {
    }

    /**
     * Dispatches count after the standard signature has accepted its receiver.
     * @param CallableIR $caller Standard model graph
     * @param Instruction $instruction Intrinsic destination
     * @param State $state Bound arguments
     * @param list<Term> $values Receiver and count mode
     * @return list<State> Count results and invocation exceptions
     */
    public function apply(CallableIR $caller, Instruction $instruction, State $state, array $values): array
    {
        $receiver = $values[0];
        $mode = $values[1] ?? Term::constant(0);
        if ($mode->kind === 'constant' && !in_array($mode->literal, [0, 1], true)) {
            $state->completion = new Completion('throw', new Term('throwable', 'ValueError'));
            return [$state];
        }
        $class = $receiver->attributes['class'] ?? '';
        $method = is_string($class) ? (new Dispatch($this->machine->context->program))->method($class, 'count') : null;
        if ($mode->kind !== 'constant' || $method === null || ($this->machine->context->program->callable($method)->returnType ?? '') !== 'int') {
            return (new UnknownCall($this->machine->context))->apply($state, $instruction, [], $receiver, 'UNSUPPORTED_MODEL_CASE');
        }
        return (new CallExecutor($this->machine))->symbol($method, [], $state, $instruction, $receiver, strict: $caller->strict);
    }
}
