<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Operation;

use Deriver\Deriver\Evaluator\Execution\Call\CallExecutor;
use Deriver\Deriver\Evaluator\Execution\Call\UnknownCall;
use Deriver\Deriver\Evaluator\Execution\Completion\Completion;
use Deriver\Deriver\Evaluator\Execution\Evaluator;
use Deriver\Deriver\Evaluator\Execution\State;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Graph\CallableGraph;
use Deriver\Deriver\Lowerer\Graph\Instruction;
use Deriver\Deriver\Lowerer\Hierarchy\Dispatch;

/**
 * Executes Countable::count using the captured receiver and ordinary source effects.
 * @visibility Deriver\Deriver\Evaluator
 * @visibility Deriver\Deriver\Evaluator\Execution
 */
final class CountableCalls
{
    /**
     * @param Evaluator $machine Shared source evaluator
     */
    public function __construct(public readonly Evaluator $machine)
    {
    }

    /**
     * Dispatches count after the standard signature has accepted its receiver.
     * @param CallableGraph $caller Standard evaluator graph
     * @param Instruction $instruction Intrinsic destination
     * @param State $state Bound arguments
     * @param list<Term> $values Receiver and count mode
     * @return list<State> Count results and invocation exceptions
     */
    public function apply(CallableGraph $caller, Instruction $instruction, State $state, array $values): array
    {
        $receiver = $values[0];
        $mode = $values[1] ?? Term::constant(0);
        if ($mode->kind === \Deriver\Deriver\Evaluator\TermKind::Constant && !in_array($mode->literal, [0, 1], true)) {
            $state->completion = new Completion(\Deriver\Deriver\Evaluator\Execution\Completion\CompletionKind::Throw, new Term(\Deriver\Deriver\Evaluator\TermKind::Throwable, 'ValueError'));
            return [$state];
        }
        $class = $receiver->attributes['class'] ?? '';
        $method = is_string($class) ? (new Dispatch($this->machine->context->program))->method($class, 'count') : null;
        if ($mode->kind !== \Deriver\Deriver\Evaluator\TermKind::Constant || $method === null || ($this->machine->context->program->callable($method)->returnType ?? '') !== 'int') {
            return (new UnknownCall($this->machine->context))->apply($state, $instruction, [], $receiver, 'UNSUPPORTED_EVALUATOR_CASE');
        }
        return (new CallExecutor($this->machine))->symbol($method, [], $state, $instruction, $receiver, strict: $caller->strict);
    }
}
