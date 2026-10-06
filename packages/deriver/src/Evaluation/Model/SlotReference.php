<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Model;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Call\TypeBinding;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Transfer\ReferenceAssignment;
use Deriver\Memory\ReferenceConstraint;

/**
 * Checks an abstract slot's type before binding it to an existing reference cell.
 * @visibility root
 */
final class SlotReference
{
    /**
     * @param Context $context Target types and diagnostic provenance
     */
    public function __construct(public readonly Context $context)
    {
    }

    /**
     * Shares a cell only when its value satisfies both source and slot constraints.
     * @param CallableGraph $caller Executing graph
     * @param Instruction $instruction Alias instruction
     * @param State $state Current memory
     * @return list<State>|null Bound paths, or null for an ordinary destination
     */
    public function apply(CallableGraph $caller, Instruction $instruction, State $state): ?array
    {
        $destination = $state->addresses[$instruction->operands[0] ?? ''] ?? null;
        if ($instruction->operation !== 'alias' || $destination === null || !str_starts_with($destination->root, 'model:') || count($destination->path) !== 1) {
            return null;
        }
        $source = $state->addresses[$instruction->operands[1]] ?? null;
        if ($source === null || $source->unknown) {
            return null;
        }
        $constraints = new ReferenceConstraint();
        $types = [...$constraints->find($state->memory, $source), ...$constraints->find($state->memory, $destination)];
        $check = (new ReferenceAssignment($this->context))->check($state->memory->read($source), $types, $caller->strict);
        (new TypeBinding($this->context))->report($check, $instruction->source, $state);
        $exception = $state->fork();
        $exception->completion = new Completion('throw', $check->exception());
        if ($check->mustFail) {
            return [$exception];
        }
        $state->memory->write($source, $check->value);
        $state->alias($destination, $source);
        $state->registers[$instruction->result] = $check->value;
        return $check->mayFail ? [$state, $exception] : [$state];
    }
}
