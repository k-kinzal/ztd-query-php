<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Transfer;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\InstructionTransfer;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\State;

/**
 * Reads compound-assignment storage after the right operand has completed.
 * @visibility root
 */
final class CompoundAssignment
{
    /**
     * @param Machine $machine Shared storage and call semantics
     */
    public function __construct(public readonly Machine $machine)
    {
    }

    /**
     * Performs the compound read, operator, and checked write in target order.
     * @param CallableGraph $caller Executing graph
     * @param Instruction $instruction Compound assignment with an evaluated right operand
     * @param State $state Input memory after right-operand effects
     * @return list<State> Normal or exceptional assignment paths
     */
    public function apply(CallableGraph $caller, Instruction $instruction, State $state): array
    {
        $transfer = new InstructionTransfer($this->machine);
        $read = new Instruction($instruction->id . ':read', 'read', $instruction->source, $instruction->result, [$instruction->operands[0]], attributes: ['compound' => true]);
        $results = [];
        foreach ($transfer->apply($caller, $read, $state) as $path) {
            if ($path->completion->kind !== 'normal') {
                $results[] = $path;
                continue;
            }
            $value = (new PureStep($this->machine->context))->binary($instruction, $path->value($instruction->result), $path->value($instruction->operands[1]));
            if ($value->kind === 'throwable') {
                $path->completion = new Completion('throw', $value);
                $results[] = $path;
                continue;
            }
            $register = $instruction->id . ':assigned';
            $path->registers[$register] = $value;
            $write = new Instruction($instruction->id . ':write', 'write', $instruction->source, $instruction->result, [$instruction->operands[0], $register]);
            array_push($results, ...$transfer->apply($caller, $write, $path));
        }
        return $results;
    }
}
