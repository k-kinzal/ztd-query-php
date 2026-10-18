<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Transfer;

use Deriver\Deriver\Evaluator\Execution\Completion\Completion;
use Deriver\Deriver\Evaluator\Execution\Evaluator;
use Deriver\Deriver\Evaluator\Execution\InstructionTransfer;
use Deriver\Deriver\Evaluator\Execution\State;
use Deriver\Deriver\Lowerer\Graph\CallableGraph;
use Deriver\Deriver\Lowerer\Graph\Instruction;

/**
 * Reads compound-assignment storage after the right operand has completed.
 * @visibility Deriver\Deriver\Evaluator
 * @visibility Deriver\Deriver\Evaluator\Execution
 */
final class CompoundAssignment
{
    /**
     * @param Evaluator $machine Shared storage and call semantics
     */
    public function __construct(public readonly Evaluator $machine)
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
        $read = new \Deriver\Deriver\Evaluator\Call\BoundInstruction($instruction->id . ':read', 'read', $instruction->source, $instruction->result, [$instruction->operands[0]], attributes: ['compound' => true]);
        $results = [];
        foreach ($transfer->apply($caller, $read, $state) as $path) {
            if ($path->completion->kind !== \Deriver\Deriver\Evaluator\Execution\Completion\CompletionKind::Normal) {
                $results[] = $path;
                continue;
            }
            $value = (new PureStep($this->machine->context))->binary($instruction, $path->value($instruction->result), $path->value($instruction->operands[1]));
            if ($value->kind === \Deriver\Deriver\Evaluator\TermKind::Throwable) {
                $path->completion = new Completion(\Deriver\Deriver\Evaluator\Execution\Completion\CompletionKind::Throw, $value);
                $results[] = $path;
                continue;
            }
            $register = $instruction->id . ':assigned';
            $path->registers[$register] = $value;
            $write = new \Deriver\Deriver\Evaluator\Call\BoundInstruction($instruction->id . ':write', 'write', $instruction->source, $instruction->result, [$instruction->operands[0], $register]);
            array_push($results, ...$transfer->apply($caller, $write, $path));
        }
        return $results;
    }
}
