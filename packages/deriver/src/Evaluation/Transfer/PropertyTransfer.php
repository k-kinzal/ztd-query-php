<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Transfer;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Call\TypeBinding;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\Offset\Path;
use Deriver\Evaluation\Offset\Transfer;
use Deriver\Evaluation\State;
use Deriver\Memory\Location;
use Deriver\Memory\ReferenceConstraint;
use Deriver\Value\Term;

/**
 * Applies property type, visibility, initialization, and implicit call semantics.
 * @visibility root
 */
final class PropertyTransfer
{
    /**
     * @param Machine $machine Shared evaluator for defaults and magic methods
     */
    public function __construct(public readonly Machine $machine)
    {
    }

    /**
     * Transfers an operation on a previously evaluated property address.
     * @param CallableGraph $caller Executing callable
     * @param Instruction $instruction Read or mutation
     * @param State $state Current path
     * @param PropertySlot $slot Property declaration context
     * @return list<State> Normal and exceptional alternatives
     */
    public function apply(CallableGraph $caller, Instruction $instruction, State $state, PropertySlot $slot): array
    {
        $address = $state->addresses[$instruction->operands[0]];
        $property = $slot->declaration;
        $boundary = (new PropertyAccessCheck($this->machine))->apply($instruction, $state, $slot, $address);
        if ($boundary !== null) {
            return $boundary;
        }
        $accessible = (new PropertyLookup($this->machine->context->program))->accessible($property, $slot->scope);
        if (!$accessible || $property === null) {
            $magic = (new PropertyMagic($this->machine))->apply($caller, $instruction, $state, $slot);
            if ($magic !== null) {
                return $magic;
            }
        }
        if (!$accessible && $instruction->operation === 'read-silent') {
            $state->registers[$instruction->result] = Term::constant(null);
            return [$state];
        }
        if (!$accessible || $slot->static && ($property === null || !$property->static)) {
            return [$this->error($state, 'Error')];
        }
        if ($slot->static && (!isset($state->memory->cells[$address->root]) || $state->memory->cells[$address->root]->kind === 'array' && ($state->memory->cells[$address->root]->attributes['open'] ?? false) !== true && !isset($state->memory->cells[$address->root]->operands[$address->path[0]]))) {
            $base = isset($state->offsets[$instruction->operands[0]]) ? (new Path($this->machine->context))->chain($state, $instruction->operands[0])['base'] : $address;
            return $this->initialize($caller, $instruction, $state, $slot, $base);
        }
        return $this->uncertain($caller, $instruction, $state, $slot, $address) ?? $this->transfer($caller, $instruction, $state, $slot, $address);
    }

    /**
     * Applies an operation after resolving access, default initialization, and receiver checks.
     * @param CallableGraph $caller Executing graph
     * @param Instruction $instruction Storage operation
     * @param State $state Accessible path
     * @param PropertySlot $slot Property context
     * @param Location $address Property storage
     * @return list<State> Normal or exceptional alternatives
     */
    public function transfer(CallableGraph $caller, Instruction $instruction, State $state, PropertySlot $slot, Location $address): array
    {
        if (isset($state->offsets[$instruction->operands[0]])) {
            return (new Transfer($this->machine))->apply($caller, $instruction, $state);
        }
        $property = $slot->declaration;
        if ($instruction->operation === 'read' && $property !== null && $property->type !== 'mixed' && $state->memory->read($address)->kind === 'uninitialized') {
            return [$this->error($state, 'Error')];
        }
        if ($instruction->operation === 'reference' && $property !== null) {
            return (new PropertyReference($this->machine))->read($caller, $instruction, $state, $slot, $address);
        }
        if ($instruction->operation === 'alias' && $property !== null) {
            return (new PropertyReference($this->machine))->bind($caller, $instruction, $state, $slot, $address);
        }
        if ($instruction->operation === 'increment' && $property !== null) {
            return (new PropertyReference($this->machine))->increment($caller, $instruction, $state, $slot, $address);
        }
        if ($instruction->operation === 'write' && $property !== null) {
            return $this->write($caller, $instruction, $state, $slot, $address);
        }
        $unsetUninitialized = $instruction->operation === 'unset' && $state->memory->read($address)->kind === 'uninitialized' && strcasecmp($slot->scope, $property->className ?? '') === 0;
        if ($property?->readonly === true && !$unsetUninitialized && in_array($instruction->operation, ['increment', 'reference', 'alias', 'unset'], true)) {
            return [$this->error($state, 'Error')];
        }
        $state->registers[$instruction->result] = (new MemoryStep($this->machine->context))->evaluate($caller, $instruction, $state);
        return [$state];
    }

    /**
     * Validates a property assignment before making its state change visible.
     * @param CallableGraph $caller Executing callable
     * @param Instruction $instruction Assignment
     * @param State $state Input state
     * @param PropertySlot $slot Declaration and scope
     * @param Location $address Property storage
     * @return list<State> Assignment result and possible type error
     */
    public function write(CallableGraph $caller, Instruction $instruction, State $state, PropertySlot $slot, Location $address): array
    {
        $property = $slot->declaration;
        if ($property === null) {
            return [$this->error($state, 'Error')];
        }
        $initialized = $state->memory->read($address)->kind !== 'uninitialized';
        $cloneWrite = isset($state->memory->cloneWrites[$address->root][$address->path[0]]);
        if ($property->readonly && ($initialized && !$cloneWrite || strcasecmp($slot->scope, $property->className) !== 0)) {
            return [$this->error($state, 'Error')];
        }
        $value = $state->value($instruction->operands[1]);
        $type = (new TypeBinding($this->machine->context))->scope($property->type, $property->className, $state->lateStaticClass);
        $types = (new ReferenceConstraint())->find($state->memory, $address);
        $check = (new ReferenceAssignment($this->machine->context))->check($value, [$type, ...$types], $caller->strict);
        (new TypeBinding($this->machine->context))->report($check, $instruction->source, $state);
        $exception = $state->fork();
        $exception->completion = new Completion('throw', $check->exception());
        if ($check->mustFail) {
            return [$exception];
        }
        unset($state->memory->cloneWrites[$address->root][$address->path[0]]);
        $state->memory->write($address, $check->value);
        $state->registers[$instruction->result] = $check->value;
        return $check->mayFail ? [$state, $exception] : [$state];
    }

    /**
     * Evaluates a static property's default once in each memory state.
     * @param CallableGraph $caller Executing callable
     * @param Instruction $instruction Pending property access
     * @param State $state Uninitialized static storage
     * @param PropertySlot $slot Captured declaration
     * @param Location $address Static property address
     * @return list<State> Initialized access results
     */
    public function initialize(CallableGraph $caller, Instruction $instruction, State $state, PropertySlot $slot, Location $address): array
    {
        $property = $slot->declaration;
        $initial = $property?->type === 'mixed' ? Term::constant(null) : new Term('uninitialized', attributes: ['type' => $property->type ?? 'mixed']);
        $state->memory->write($address, $initial);
        if ($property?->default === null) {
            return $this->apply($caller, $instruction, $state, $slot);
        }
        $paths = [];
        foreach ($this->machine->run($property->default, new State(clone $state->memory)) as $exit) {
            $next = $state->fork();
            $next->memory = $exit->memory;
            if ($exit->completion->kind === 'throw') {
                $next->completion = $exit->completion;
                $paths[] = $next;
                continue;
            }
            $next->memory->write($address, $exit->completion->value ?? $initial);
            array_push($paths, ...$this->apply($caller, $instruction, $next, $slot));
        }
        return $paths;
    }

    /**
     * Produces an exceptional path without performing the invalid write.
     * @param State $state Input path
     * @param string $class Throwable type
     * @return State Exceptional state
     */
    public function error(State $state, string $class): State
    {
        $state->completion = new Completion('throw', new Term('throwable', $class));
        return $state;
    }

    /**
     * Retains the possibility that an unknown call unset a declared property.
     * @param CallableGraph $caller Executing graph
     * @param Instruction $instruction Property operation
     * @param State $state Input path
     * @param PropertySlot $slot Property contract
     * @param Location $address Property storage
     * @return list<State>|null Initialized and absent alternatives, or a known presence
     */
    public function uncertain(CallableGraph $caller, Instruction $instruction, State $state, PropertySlot $slot, Location $address): ?array
    {
        $value = $state->memory->read($address);
        $unknown = ($value->attributes['maybeUninitialized'] ?? false) === true || $value->kind === 'array-read' && ($state->memory->cells[$address->root]->kind ?? '') === 'opaque';
        if (!$unknown || $slot->declaration === null) {
            return null;
        }
        $absent = $state->fork();
        $absent->memory->write($address, new Term('uninitialized'));
        $attributes = $value->attributes;
        unset($attributes['maybeUninitialized']);
        $present = new Term('opaque', is_string($value->literal) ? $value->literal : 'UNKNOWN_PROPERTY', $value->operands, [...$attributes, 'type' => $slot->declaration->type], $value->secret);
        $state->memory->write($address, $present);
        return [...$this->transfer($caller, $instruction, $state, $slot, $address), ...$this->transfer($caller, $instruction, $absent, $slot, $address)];
    }
}
