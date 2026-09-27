<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Offset;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Call\TypeBinding;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Transfer\ReferenceAssignment;
use Deriver\Memory\Location;
use Deriver\Memory\ReferenceConstraint;
use Deriver\Value\Arrays;
use Deriver\Value\Term;

/**
 * Resolves evaluated offset chains while preserving partial array creation on failure.
 * @visibility root
 */
final class Path
{
    /**
     * @param Context $context Target declarations and diagnostics
     */
    public function __construct(public readonly Context $context)
    {
    }

    /**
     * Finds the underlying storage and ordered offset registers.
     * @param State $state Address metadata
     * @param string $register Final address register
     * @return array{base: Location, offsets: list<string>} Original storage and chain
     */
    public function chain(State $state, string $register): array
    {
        $offsets = [];
        while (isset($state->offsets[$register])) {
            $offsets[] = $register;
            $register = $state->offsets[$register]->parent;
        }
        return ['base' => $state->addresses[$register] ?? new Location('unknown', unknown: true), 'offsets' => array_reverse($offsets)];
    }

    /**
     * Reads each container without allocating storage for scalar or absent values.
     * @param State $state Input memory
     * @param Instruction $instruction Read operation
     * @return Term|ProtocolAccess Final read result or an implicit offset protocol call
     */
    public function read(State $state, Instruction $instruction): Term|ProtocolAccess
    {
        $chain = $this->chain($state, $instruction->operands[0]);
        $value = $state->memory->read($chain['base']);
        $silent = $instruction->operation === 'read-silent';
        if ($value->kind === 'uninitialized' && ($value->attributes['type'] ?? 'mixed') !== 'mixed' && !$silent) {
            return new Term('throwable', 'Error');
        }
        foreach ($chain['offsets'] as $position => $register) {
            if ($value->kind === 'object' && !(new Reader($this->context))->plainObject($value)) {
                return new ProtocolAccess($value, $state->offsets[$register]->key, array_slice($chain['offsets'], $position + 1));
            }
            $value = (new Reader($this->context))->read($value, $state->offsets[$register]->key, $instruction, $state, $silent);
            if ($value->kind === 'throwable' || $value->kind === 'opaque' && $value->literal === 'OFFSET_OPERATION') {
                return $value;
            }
        }
        return $value;
    }

    /**
     * Resolves a write, reference, or unset, creating intermediate arrays only when allowed.
     * @param CallableGraph $caller Executing callable
     * @param State $state Mutable path memory
     * @param Instruction $instruction Storage operation
     * @return Location|StringAccess|ProtocolAccess|Term Resolved storage, overloaded access, or terminal result
     */
    public function locate(CallableGraph $caller, State $state, Instruction $instruction): Location|StringAccess|ProtocolAccess|Term
    {
        $chain = $this->chain($state, $instruction->operands[0]);
        $address = $chain['base'];
        $create = $instruction->operation !== 'unset';
        foreach ($chain['offsets'] as $position => $register) {
            $container = $this->prepare($caller, $state, $address, $instruction, $create);
            $key = $state->offsets[$register]->key;
            if ($container->kind === 'object') {
                return new ProtocolAccess($container, $key, array_slice($chain['offsets'], $position + 1));
            }
            if ($container->kind === 'constant' && is_string($container->literal)) {
                if ($position === count($chain['offsets']) - 1) {
                    return new StringAccess($address, $key);
                }
                $index = (new Strings($this->context))->index($key, $instruction);
                return $index->kind === 'constant' ? new Term('throwable', 'Error') : $index;
            }
            if ($container->kind !== 'array') {
                return $container;
            }
            $normalized = $key === null ? (new Arrays())->appendKey($container) : (new Reader($this->context))->key($key, $instruction);
            if ($normalized->kind === 'throwable') {
                return $normalized;
            }
            if ($normalized->kind !== 'constant' || !is_int($normalized->literal) && !is_string($normalized->literal)) {
                return Term::opaque('OFFSET_OPERATION', dependencies: [$container, ...($key === null ? [] : [$key])]);
            }
            $address = new Location($address->root, [...$address->path, $normalized->literal]);
            $state->addresses[$register] = $address;
            if ($key === null) {
                $state->offsets[$register] = new Address($state->offsets[$register]->parent, $normalized);
            }
        }
        return $address;
    }

    /**
     * Autovivifies legal containers before checking the following key.
     * @param CallableGraph $caller Calling-file type coercion mode
     * @param State $state Input memory
     * @param Location $address Current container storage
     * @param Instruction $instruction Originating storage operation
     * @param bool $create Whether absence may create an array
     * @return Term Container or a terminal result
     */
    public function prepare(CallableGraph $caller, State $state, Location $address, Instruction $instruction, bool $create): Term
    {
        $before = $state->memory->read($address);
        if ($before->kind === 'array' || $before->kind === 'constant' && is_string($before->literal)) {
            return $before;
        }
        if ($before->kind === 'uninitialized' || $before->kind === 'constant' && in_array($before->literal, [null, false], true)) {
            if (!$create) {
                if ($before->kind === 'constant' && $before->literal === false) {
                    (new Strings($this->context))->warning($instruction);
                }
                return Term::constant(null);
            }
            return $this->create($caller, $state, $address, $before, $instruction);
        }
        if ($before->kind === 'constant') {
            return new Term('throwable', 'Error');
        }
        if ((new Reader($this->context))->plainObject($before)) {
            return new Term('throwable', 'Error');
        }
        if ($before->kind === 'object') {
            return $before;
        }
        return Term::opaque('OFFSET_OPERATION', dependencies: [$before]);
    }

    /**
     * Creates an intermediate array subject to every live property reference constraint.
     * @param CallableGraph $caller Calling-file coercion mode
     * @param State $state Path memory
     * @param Location $address Container to initialize
     * @param Term $before Previous null, false, or uninitialized value
     * @param Instruction $instruction Origin
     * @return Term Created array or a type error before mutation
     */
    public function create(CallableGraph $caller, State $state, Location $address, Term $before, Instruction $instruction): Term
    {
        $array = Term::array([]);
        $types = (new ReferenceConstraint())->find($state->memory, $address);
        $check = (new ReferenceAssignment($this->context))->check($array, $types, $caller->strict);
        (new TypeBinding($this->context))->report($check, $instruction->source, $state);
        if ($check->mustFail) {
            return new Term('throwable', 'TypeError');
        }
        if ($check->mayFail) {
            return Term::opaque('OFFSET_OPERATION', dependencies: [$before]);
        }
        if ($before->kind === 'constant' && $before->literal === false) {
            (new Strings($this->context))->warning($instruction);
        }
        $state->memory->write($address, $array);
        return $array;
    }
}
