<?php

declare(strict_types=1);

namespace Deriver\Internal\Solver\Transfer;

use Deriver\Internal\IR\CallableIR;
use Deriver\Internal\IR\Instruction;
use Deriver\Internal\Memory\Location;
use Deriver\Internal\Solver\Completion;
use Deriver\Internal\Solver\Machine;
use Deriver\Internal\Solver\State;
use Deriver\Value\Term;

/**
 * Applies property checks to reference reads and increment writes.
 * @visibility root
 */
final class PropertyReference
{
    /**
     * @param Machine $machine Shared property and call semantics
     */
    public function __construct(public readonly Machine $machine)
    {
    }

    /**
     * Checks the source property before exposing its reference to another binding.
     * @param CallableIR $caller Executing graph
     * @param Instruction $instruction Reference assignment
     * @param State $state Current path
     * @return list<State> Alias or source-access exception
     */
    public function alias(CallableIR $caller, Instruction $instruction, State $state): array
    {
        $source = $instruction->operands[1];
        $probe = new Instruction($instruction->id, 'reference', $instruction->source, $instruction->result, [$source]);
        $paths = (new \Deriver\Internal\Solver\InstructionTransfer($this->machine))->apply($caller, $probe, $state);
        $result = [];
        foreach ($paths as $path) {
            if ($path->completion->kind !== 'normal') {
                $result[] = $path;
                continue;
            }
            $alias = new Instruction($instruction->id, 'alias', $instruction->source, $instruction->result, $instruction->operands, attributes: [...$instruction->attributes, 'source-prepared' => true]);
            array_push($result, ...(new \Deriver\Internal\Solver\InstructionTransfer($this->machine))->apply($caller, $alias, $path));
        }
        return $result;
    }

    /**
     * Initializes an uninitialized nullable property when its storage escapes by reference.
     * @param CallableIR $caller Executing graph
     * @param Instruction $instruction Reference read
     * @param State $state Accessible path
     * @param PropertySlot $slot Property declaration
     * @param Location $address Property storage
     * @return list<State> Reference result or unchanged invalid access
     */
    public function read(CallableIR $caller, Instruction $instruction, State $state, PropertySlot $slot, Location $address): array
    {
        if ($slot->declaration?->readonly === true) {
            return [(new PropertyTransfer($this->machine))->error($state, 'Error')];
        }
        if ($state->memory->read($address)->kind === 'uninitialized') {
            $type = (new \Deriver\Internal\Solver\Call\TypeBinding($this->machine->context))->scope($slot->declaration->type ?? 'mixed', $slot->declaration->className ?? '', $state->lateStaticClass);
            $check = (new \Deriver\Internal\Solver\Call\TypeBinding($this->machine->context))->check(Term::constant(null), $type, true);
            (new \Deriver\Internal\Solver\Call\TypeBinding($this->machine->context))->report($check, $instruction->source, $state);
            if ($check->mustFail) {
                return [(new PropertyTransfer($this->machine))->error($state, 'Error')];
            }
            $state->memory->write($address, Term::constant(null));
        }
        $state->registers[$instruction->result] = (new MemoryStep($this->machine->context))->evaluate($caller, $instruction, $state);
        return [$state];
    }

    /**
     * Type-checks the incremented value before changing the property.
     * @param CallableIR $caller Executing graph
     * @param Instruction $instruction Increment operation
     * @param State $state Current path
     * @param PropertySlot $slot Declared property
     * @param Location $address Property storage
     * @return list<State> Increment result or an unchanged exceptional state
     */
    public function increment(CallableIR $caller, Instruction $instruction, State $state, PropertySlot $slot, Location $address): array
    {
        if ($slot->declaration?->readonly === true) {
            $state->completion = new Completion('throw', new Term('throwable', 'Error'));
            return [$state];
        }
        $before = $state->memory->read($address);
        if ($before->kind === 'uninitialized' && ($slot->declaration->type ?? 'mixed') !== 'mixed') {
            return [(new PropertyTransfer($this->machine))->error($state, 'Error')];
        }
        $after = (new MemoryStep($this->machine->context))->incremented($before, $instruction);
        if ($after->kind === 'throwable') {
            $state->completion = new Completion('throw', $after);
            return [$state];
        }
        $register = $instruction->result . ':increment';
        $state->registers[$register] = $after;
        $write = new Instruction($instruction->id, 'write', $instruction->source, $instruction->result, [$instruction->operands[0], $register]);
        $paths = (new PropertyTransfer($this->machine))->write($caller, $write, $state, $slot, $address);
        foreach ($paths as $path) {
            if ($path->completion->kind === 'normal' && ($instruction->attributes['post'] ?? false) === true) {
                $path->registers[$instruction->result] = $before;
            }
        }
        return $paths;
    }

    /**
     * Binds a property to a reference only after checking their shared type obligations.
     * @param CallableIR $caller Executing graph
     * @param Instruction $instruction Reference assignment
     * @param State $state Current path
     * @param PropertySlot $slot Destination declaration
     * @param Location $address Destination property
     * @return list<State> Bound paths or unchanged type errors
     */
    public function bind(CallableIR $caller, Instruction $instruction, State $state, PropertySlot $slot, Location $address): array
    {
        if ($slot->declaration?->readonly === true) {
            $state->completion = new Completion('throw', new Term('throwable', 'Error'));
            return [$state];
        }
        $source = $state->addresses[$instruction->operands[1]];
        $types = (new \Deriver\Internal\Memory\ReferenceConstraint())->find($state->memory, $source);
        $type = (new \Deriver\Internal\Solver\Call\TypeBinding($this->machine->context))->scope($slot->declaration->type ?? 'mixed', $slot->declaration->className ?? '', $state->lateStaticClass);
        $check = (new ReferenceAssignment($this->machine->context))->check($state->memory->read($source), [$type, ...$types], $caller->strict);
        (new \Deriver\Internal\Solver\Call\TypeBinding($this->machine->context))->report($check, $instruction->source, $state);
        $exception = $state->fork();
        $exception->completion = new Completion('throw', $check->exception());
        if ($check->mustFail) {
            return [$exception];
        }
        $state->memory->write($source, $check->value);
        $state->alias($address, $source);
        $state->registers[$instruction->result] = $check->value;
        return $check->mayFail ? [$state, $exception] : [$state];
    }
}
