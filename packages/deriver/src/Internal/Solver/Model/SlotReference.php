<?php

declare(strict_types=1);

namespace Deriver\Internal\Solver\Model;

use Deriver\Internal\IR\CallableIR;
use Deriver\Internal\IR\Instruction;
use Deriver\Internal\Memory\ReferenceConstraint;
use Deriver\Internal\Solver\Call\TypeBinding;
use Deriver\Internal\Solver\Completion;
use Deriver\Internal\Solver\Context;
use Deriver\Internal\Solver\State;
use Deriver\Internal\Solver\Transfer\ReferenceAssignment;

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
     * @param CallableIR $caller Executing graph
     * @param Instruction $instruction Alias instruction
     * @param State $state Current memory
     * @return list<State>|null Bound paths, or null for an ordinary destination
     */
    public function apply(CallableIR $caller, Instruction $instruction, State $state): ?array
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
