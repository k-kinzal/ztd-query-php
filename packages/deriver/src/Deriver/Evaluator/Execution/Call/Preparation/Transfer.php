<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Call\Preparation;

use Deriver\Deriver\Evaluator\Execution\Completion\Completion;
use Deriver\Deriver\Evaluator\Execution\Evaluator;
use Deriver\Deriver\Evaluator\Execution\Havoc;
use Deriver\Deriver\Evaluator\Execution\State;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Graph\CallableGraph;
use Deriver\Deriver\Lowerer\Graph\Instruction;

/**
 * Captures call signatures and early failures before argument evaluation begins.
 * @visibility Deriver\Deriver\Evaluator
 * @visibility Deriver\Deriver\Evaluator\Execution
 */
final class Transfer
{
    /**
     * @param Evaluator $machine Shared evaluator
     */
    public function __construct(public readonly Evaluator $machine)
    {
    }

    /**
     * Prepares a call without executing its body or arguments.
     * @param CallableGraph $caller Executing callable
     * @param Instruction $instruction Preparation with target operands
     * @param State $state \Deriver\Deriver\Evaluator\Execution\State after target evaluation
     * @return list<State> Prepared path and any early resolution exceptions
     */
    public function apply(CallableGraph $caller, Instruction $instruction, State $state): array
    {
        $operation = $instruction->attributes['call-operation'] ?? 'invoke';
        $prototype = new \Deriver\Deriver\Evaluator\Call\BoundInstruction($instruction->id, is_string($operation) ? $operation : 'invoke', $instruction->source, $instruction->result, $instruction->operands, attributes: $instruction->attributes);
        $target = (new Resolution($this->machine))->resolve($caller, $prototype, $state);
        $state->callTargets[$instruction->result] = $target;
        $state->registers[$instruction->result] = new Term(\Deriver\Deriver\Evaluator\TermKind::Callable, $instruction->id);
        if ($target->error !== '') {
            $state->completion = new Completion(\Deriver\Deriver\Evaluator\Execution\Completion\CompletionKind::Throw, new Term(\Deriver\Deriver\Evaluator\TermKind::Throwable, $target->error));
            return [$state];
        }
        if ($target->signature !== null) {
            return [$state];
        }
        $reason = $prototype->operation === 'new' ? 'INCOMPLETE_SOURCE' : ($prototype->operation === 'invoke' ? 'MISSING_CALL_MODEL' : 'OPEN_DISPATCH');
        $values = array_map(fn (string $register): Term => $state->value($register), $instruction->operands);
        $name = $values[0]->literal ?? null;
        $autoload = in_array($prototype->operation, ['new', 'invoke-static'], true) || is_string($name) && str_contains($name, '::');
        if ($autoload) {
            (new Havoc())->call($state, $values, [], $reason);
            $this->machine->context->frontier($reason, $instruction->source, 'call', $values);
        }
        $exception = $state->fork();
        $exception->completion = new Completion(\Deriver\Deriver\Evaluator\Execution\Completion\CompletionKind::Throw, $autoload ? new Term(\Deriver\Deriver\Evaluator\TermKind::Throwable, 'Throwable', attributes: ['uncertain' => true]) : new Term(\Deriver\Deriver\Evaluator\TermKind::Throwable, 'Error'));
        return [$state, $exception];
    }
}
