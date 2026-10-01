<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Call\Preparation;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Havoc;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\State;
use Deriver\Value\Term;

/**
 * Captures call signatures and early failures before argument evaluation begins.
 * @visibility root
 */
final class Transfer
{
    /**
     * @param Machine $machine Shared evaluator
     */
    public function __construct(public readonly Machine $machine)
    {
    }

    /**
     * Prepares a call without executing its body or arguments.
     * @param CallableGraph $caller Executing callable
     * @param Instruction $instruction Preparation with target operands
     * @param State $state State after target evaluation
     * @return list<State> Prepared path and any early resolution exceptions
     */
    public function apply(CallableGraph $caller, Instruction $instruction, State $state): array
    {
        $operation = $instruction->attributes['call-operation'] ?? 'invoke';
        $prototype = new Instruction($instruction->id, is_string($operation) ? $operation : 'invoke', $instruction->source, $instruction->result, $instruction->operands, attributes: $instruction->attributes);
        $target = (new Resolution($this->machine))->resolve($caller, $prototype, $state);
        $state->callTargets[$instruction->result] = $target;
        $state->registers[$instruction->result] = new Term('callable', $instruction->id);
        if ($target->error !== '') {
            $state->completion = new Completion('throw', new Term('throwable', $target->error));
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
        $exception->completion = new Completion('throw', $autoload ? new Term('throwable', 'Throwable', attributes: ['uncertain' => true]) : new Term('throwable', 'Error'));
        return [$state, $exception];
    }
}
