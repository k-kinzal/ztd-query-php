<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Offset;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Havoc;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Transfer\MemoryStep;
use Deriver\Evaluation\Transfer\ReferenceAssignment;
use Deriver\Value\Term;

/**
 * Applies offset operations after container, access, and reference validation.
 * @visibility root
 */
final class Transfer
{
    /**
     * @param Machine $machine Shared execution context
     */
    public function __construct(public readonly Machine $machine)
    {
    }

    /**
     * Transfers one offset read or mutation with explicit normal and exceptional results.
     * @param CallableGraph $caller Executing callable
     * @param Instruction $instruction Storage operation
     * @param State $state Input path
     * @return list<State> Normal and exceptional paths
     */
    public function apply(CallableGraph $caller, Instruction $instruction, State $state): array
    {
        $path = new Path($this->machine->context);
        if (($instruction->attributes['compound'] ?? false) === true) {
            return $this->compound($caller, $instruction, $state);
        }
        if (in_array($instruction->operation, ['read', 'read-silent'], true)) {
            $value = $path->read($state, $instruction);
            return $value instanceof ProtocolAccess ? (new Protocol($this->machine))->apply($caller, $instruction, $state, $value) : $this->result($state, $instruction, $value);
        }
        $address = $path->locate($caller, $state, $instruction);
        if ($address instanceof Term) {
            return $this->result($state, $instruction, $address);
        }
        if ($address instanceof StringAccess) {
            return $this->string($instruction, $state, $address);
        }
        if ($address instanceof ProtocolAccess) {
            return (new Protocol($this->machine))->apply($caller, $instruction, $state, $address);
        }
        $state->addresses[$instruction->operands[0]] = $address;
        if ($instruction->operation === 'increment' && $state->memory->read($address)->kind === 'uninitialized') {
            (new Strings($this->machine->context))->warning($instruction);
        }
        if ($instruction->operation === 'reference' && $state->memory->read($address)->kind === 'uninitialized') {
            $state->memory->write($address, Term::constant(null));
        }
        $reference = (new ReferenceAssignment($this->machine->context))->apply($caller, $instruction, $state);
        if ($reference !== null) {
            return $reference;
        }
        return $this->result($state, $instruction, (new MemoryStep($this->machine->context))->evaluate($caller, $instruction, $state));
    }

    /**
     * Initializes missing array slots before a compound operator can fail.
     * @param CallableGraph $caller Executing callable
     * @param Instruction $instruction Compound-assignment read
     * @param State $state Path after the right operand
     * @return list<State> Existing value or failure after partial array creation
     */
    public function compound(CallableGraph $caller, Instruction $instruction, State $state): array
    {
        $address = (new Path($this->machine->context))->locate($caller, $state, $instruction);
        if ($address instanceof ProtocolAccess) {
            return (new Protocol($this->machine))->get($caller, $instruction, $state, $address);
        }
        if ($address instanceof Term) {
            return $this->result($state, $instruction, $address);
        }
        if ($address instanceof StringAccess) {
            $key = (new Strings($this->machine->context))->index($address->key, $instruction);
            return $this->result($state, $instruction, $key->kind === 'constant' ? new Term('throwable', 'Error') : $key);
        }
        $value = $state->memory->read($address);
        if ($value->kind === 'uninitialized') {
            (new Strings($this->machine->context))->warning($instruction);
            $value = Term::constant(null);
            $state->memory->write($address, $value);
        }
        return $this->result($state, $instruction, $value);
    }

    /**
     * Updates a string cell while returning the byte assignment expression.
     * @param Instruction $instruction Storage operation
     * @param State $state Path memory
     * @param StringAccess $access Complete string storage and original offset
     * @return list<State> Mutation result or error without mutation
     */
    public function string(Instruction $instruction, State $state, StringAccess $access): array
    {
        if ($instruction->operation === 'unset') {
            return $this->result($state, $instruction, new Term('throwable', 'Error'));
        }
        if ($instruction->operation !== 'write') {
            $key = (new Strings($this->machine->context))->index($access->key, $instruction);
            return $this->result($state, $instruction, $key->kind === 'constant' ? new Term('throwable', 'Error') : $key);
        }
        $write = (new Strings($this->machine->context))->write($state->memory->read($access->container), $access->key, $state->value($instruction->operands[1]), $instruction);
        if ($write['result']->kind !== 'throwable') {
            $state->memory->write($access->container, $write['value']);
        }
        return $this->result($state, $instruction, $write['result']);
    }

    /**
     * Records concrete errors and preserves both exit kinds at unresolved protocols.
     * @param State $state Input path after any completed effects
     * @param Instruction $instruction Originating operation
     * @param Term $value Result or terminal condition
     * @return list<State> Complete alternatives for this transfer
     */
    public function result(State $state, Instruction $instruction, Term $value): array
    {
        if ($value->kind === 'array-read') {
            return (new ReadCandidates($this->machine->context))->apply($state, $instruction, $value);
        }
        if ($value->kind === 'opaque' && $value->literal === 'OFFSET_OPERATION') {
            return $this->boundary($state, $instruction, $value);
        }
        $state->registers[$instruction->result] = $value;
        if ($value->kind === 'throwable') {
            $state->completion = new Completion('throw', $value);
        }
        return [$state];
    }

    /**
     * Preserves potential implicit calls, writes, and exceptions at unknown offset operations.
     * @param State $state State after preceding effects
     * @param Instruction $instruction Unsupported protocol or symbolic container
     * @param Term $value Unresolved operation with its reachable operands
     * @return list<State> Inclusive normal and unknown-throwable alternatives
     */
    public function boundary(State $state, Instruction $instruction, Term $value): array
    {
        $references = in_array($instruction->operation, ['write', 'alias', 'increment', 'unset', 'reference'], true) ? [(new Path($this->machine->context))->chain($state, $instruction->operands[0])['base']] : [];
        (new Havoc())->call($state, array_values($value->operands), $references, 'OFFSET_OPERATION');
        $state->registers[$instruction->result] = $this->machine->context->frontier('UNSUPPORTED_LANGUAGE_FEATURE', $instruction->source, 'offset-protocol', array_values($value->operands));
        $exception = $state->fork();
        $exception->completion = new Completion('throw', new Term('throwable', 'Throwable', attributes: ['uncertain' => true]));
        return [$state, $exception];
    }
}
